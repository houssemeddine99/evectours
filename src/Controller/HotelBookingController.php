<?php

namespace App\Controller;

use App\Entity\HotelBooking;
use App\Entity\User;
use App\Repository\HotelBookingRepository;
use App\Service\CurrencyService;
use App\Service\FlouciPaymentService;
use App\Service\HotelbedsService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * In-app hotel booking: checkrate → guest details → Flouci payment → Hotelbeds booking → voucher.
 */
class HotelBookingController extends AbstractController
{
    public function __construct(
        private readonly HotelbedsService $hotels,
        private readonly FlouciPaymentService $flouci,
        private readonly CurrencyService $currency,
        private readonly HotelBookingRepository $bookings,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return array{checkIn:string,checkOut:string,adults:int,children:int,rooms:int,nights:int} */
    private function stayFrom(Request $r): array
    {
        $in  = trim((string) $r->get('checkIn', ''));
        $out = trim((string) $r->get('checkOut', ''));
        $nights = ($in && $out) ? (int) max(1, (strtotime($out) - strtotime($in)) / 86400) : 1;
        return [
            'checkIn'  => $in,
            'checkOut' => $out,
            'adults'   => max(1, (int) $r->get('adults', 2)),
            'children' => max(0, min(6, (int) $r->get('children', 0))),
            'rooms'    => max(1, (int) $r->get('rooms', 1)),
            'nights'   => $nights,
        ];
    }

    #[Route('/hotel/{code}/book', name: 'hotel_book_form', methods: ['GET'], requirements: ['code' => '\d+'])]
    public function form(string $code, Request $request): Response
    {
        $rateKey = (string) $request->query->get('rateKey', '');
        if ($rateKey === '') {
            return $this->redirectToRoute('hotel_detail', ['code' => $code]);
        }

        $stay  = $this->stayFrom($request);
        $check = $this->hotels->checkRate($rateKey);

        if ($check === null || $check['net'] === null) {
            // Rate no longer available — send the user back to pick a fresh one.
            $this->addFlash('hotel_error', "Ce tarif n'est plus disponible. Merci de re-sélectionner une chambre.");
            return $this->redirectToRoute('hotel_detail', array_merge(['code' => $code], [
                'checkIn' => $stay['checkIn'], 'checkOut' => $stay['checkOut'],
                'adults' => $stay['adults'], 'children' => $stay['children'], 'rooms' => $stay['rooms'],
            ]));
        }

        $amountTnd = $this->currency->convertCurrency((float) $check['net'], $check['currency'], 'TND');
        $user      = $this->getUser();

        return $this->render('travel/hotel_booking_form.html.twig', [
            'active_nav' => 'hotels',
            'code'       => $code,
            'rateKey'    => $rateKey, // original key; re-checkrated on submit for a fresh bookable key
            'check'      => $check,
            'stay'       => $stay,
            'amount_tnd' => round($amountTnd, 3),
            'amount_tnd_display' => $this->currency->format($amountTnd, 'TND'),
            'prefill'    => $user instanceof User ? ['email' => $user->getEmail(), 'name' => $user->getUsername()] : ['email' => '', 'name' => ''],
        ]);
    }

    #[Route('/hotel/{code}/book', name: 'hotel_book_submit', methods: ['POST'], requirements: ['code' => '\d+'])]
    public function submit(string $code, Request $request): Response
    {
        $rateKey = (string) $request->request->get('rateKey', '');
        $name    = trim((string) $request->request->get('holder_name', ''));
        $surname = trim((string) $request->request->get('holder_surname', ''));
        $email   = trim((string) $request->request->get('email', ''));
        $phone   = trim((string) $request->request->get('phone', ''));
        $stay    = $this->stayFrom($request);

        // Re-price right before charging (also yields a fresh BOOKABLE rateKey).
        $check = $rateKey !== '' ? $this->hotels->checkRate($rateKey) : null;
        if ($check === null || $check['net'] === null) {
            $this->addFlash('hotel_error', "Ce tarif n'est plus disponible. Merci de re-sélectionner une chambre.");
            return $this->redirectToRoute('hotel_detail', ['code' => $code, 'checkIn' => $stay['checkIn'], 'checkOut' => $stay['checkOut']]);
        }

        if ($name === '' || $surname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $amountTnd = $this->currency->convertCurrency((float) $check['net'], $check['currency'], 'TND');
            return $this->render('travel/hotel_booking_form.html.twig', [
                'active_nav' => 'hotels', 'code' => $code, 'rateKey' => $rateKey, 'check' => $check,
                'stay' => $stay, 'amount_tnd' => round($amountTnd, 3), 'amount_tnd_display' => $this->currency->format($amountTnd, 'TND'),
                'prefill' => ['email' => $email, 'name' => $name],
                'error'   => 'Merci de renseigner votre nom, prénom et un e-mail valide.',
            ], new Response('', 422));
        }

        $amountTnd = $this->currency->convertCurrency((float) $check['net'], $check['currency'], 'TND');

        $booking = (new HotelBooking())
            ->setHotelCode($code)
            ->setHotelName($check['hotel_name'])
            ->setRoomName($check['room_name'])
            ->setBoard($check['board'])
            ->setCheckIn($stay['checkIn'] ? new \DateTime($stay['checkIn']) : new \DateTime())
            ->setCheckOut($stay['checkOut'] ? new \DateTime($stay['checkOut']) : new \DateTime('+1 day'))
            ->setNights($stay['nights'])
            ->setAdults($stay['adults'])
            ->setChildren($stay['children'])
            ->setRooms($stay['rooms'])
            ->setHolderName($name)
            ->setHolderSurname($surname)
            ->setEmail($email)
            ->setPhone($phone !== '' ? $phone : null)
            ->setAmountEur((float) $check['net'])
            ->setCurrency($check['currency'])
            ->setAmountTnd(round($amountTnd, 3))
            ->setRateKey($check['rate_key']);

        if ($this->getUser() instanceof User) {
            $booking->setUserId($this->getUser()->getId());
        }
        $this->bookings->save($booking);

        // Kick off Flouci payment.
        $success = $this->generateUrl('hotel_book_return', ['id' => $booking->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $fail    = $this->generateUrl('hotel_book_return', ['id' => $booking->getId(), 'failed' => 1], UrlGeneratorInterface::ABSOLUTE_URL);
        $payment = $this->flouci->createPayment($booking->getId(), $booking->getAmountTnd(), $success, $fail);

        if ($payment === null || empty($payment['link'])) {
            // Payment gateway unavailable — keep the booking pending and let staff follow up.
            $this->logger->warning('Hotel booking ' . $booking->getReference() . ': Flouci payment unavailable.');
            return $this->redirectToRoute('hotel_book_confirmation', ['ref' => $booking->getReference()]);
        }

        $booking->setFlouciPaymentId($payment['payment_id']);
        $this->bookings->save($booking);

        return $this->redirect($payment['link']);
    }

    #[Route('/hotel-booking/{id}/return', name: 'hotel_book_return', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function paymentReturn(int $id, Request $request): Response
    {
        $booking = $this->bookings->find($id);
        if (!$booking) {
            throw $this->createNotFoundException();
        }
        // Idempotent: if already finalised, just show it.
        if (in_array($booking->getStatus(), [HotelBooking::STATUS_CONFIRMED, HotelBooking::STATUS_BOOK_FAIL], true)) {
            return $this->redirectToRoute('hotel_book_confirmation', ['ref' => $booking->getReference()]);
        }

        $paymentId = (string) ($request->query->get('payment_id') ?: $booking->getFlouciPaymentId());
        $status    = $paymentId !== '' ? $this->flouci->verifyPayment($paymentId) : null;

        if ($status !== 'SUCCESS') {
            $booking->setStatus(HotelBooking::STATUS_PAY_FAIL);
            $this->bookings->save($booking);
            return $this->redirectToRoute('hotel_book_confirmation', ['ref' => $booking->getReference()]);
        }

        // Paid — now confirm the reservation with Hotelbeds.
        $booking->setStatus(HotelBooking::STATUS_PAID);
        $this->bookings->save($booking);

        $result = $this->hotels->book([
            'rateKey'         => $booking->getRateKey(),
            'holderName'      => $booking->getHolderName(),
            'holderSurname'   => $booking->getHolderSurname(),
            'adults'          => $booking->getAdults(),
            'children'        => $booking->getChildren(),
            'clientReference' => $booking->getReference(),
            'remark'          => 'Booked via Evec Tours',
        ]);

        if (!empty($result['reference'])) {
            $booking->setHotelbedsReference($result['reference'])->setStatus(HotelBooking::STATUS_CONFIRMED);
        } else {
            $booking->setStatus(HotelBooking::STATUS_BOOK_FAIL);
            $this->logger->error('Hotel booking ' . $booking->getReference() . ' paid but Hotelbeds book failed: ' . ($result['error'] ?? '?'));
        }
        $this->bookings->save($booking);

        return $this->redirectToRoute('hotel_book_confirmation', ['ref' => $booking->getReference()]);
    }

    #[Route('/hotel-booking/{ref}', name: 'hotel_book_confirmation', methods: ['GET'], requirements: ['ref' => 'EVEC-[A-Z0-9]+'])]
    public function confirmation(string $ref): Response
    {
        $booking = $this->bookings->findOneBy(['reference' => $ref]);
        if (!$booking) {
            throw $this->createNotFoundException();
        }
        return $this->render('travel/hotel_booking_confirmation.html.twig', [
            'active_nav' => 'hotels',
            'b'          => $booking,
        ]);
    }
}
