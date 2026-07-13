<?php

namespace App\Entity;

use App\Repository\HotelBookingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HotelBookingRepository::class)]
#[ORM\Table(name: 'hotel_bookings')]
class HotelBooking
{
    // Lifecycle: PENDING_PAYMENT -> PAID -> CONFIRMED
    //            PENDING_PAYMENT -> PAYMENT_FAILED
    //            PAID -> BOOKING_FAILED (paid but Hotelbeds book failed — needs staff)
    public const STATUS_PENDING   = 'PENDING_PAYMENT';
    public const STATUS_PAID      = 'PAID';
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_PAY_FAIL  = 'PAYMENT_FAILED';
    public const STATUS_BOOK_FAIL = 'BOOKING_FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private string $reference = '';

    #[ORM\Column(name: 'hotel_code', length: 20)]
    private string $hotelCode = '';

    #[ORM\Column(name: 'hotel_name', length: 255)]
    private string $hotelName = '';

    #[ORM\Column(name: 'room_name', length: 255, nullable: true)]
    private ?string $roomName = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $board = null;

    #[ORM\Column(name: 'check_in', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $checkIn = null;

    #[ORM\Column(name: 'check_out', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $checkOut = null;

    #[ORM\Column]
    private int $nights = 1;

    #[ORM\Column]
    private int $adults = 2;

    #[ORM\Column]
    private int $children = 0;

    #[ORM\Column]
    private int $rooms = 1;

    #[ORM\Column(name: 'holder_name', length: 100)]
    private string $holderName = '';

    #[ORM\Column(name: 'holder_surname', length: 100)]
    private string $holderSurname = '';

    #[ORM\Column(length: 150)]
    private string $email = '';

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'amount_eur', type: Types::FLOAT)]
    private float $amountEur = 0;

    #[ORM\Column(name: 'amount_tnd', type: Types::FLOAT)]
    private float $amountTnd = 0;

    #[ORM\Column(length: 10)]
    private string $currency = 'EUR';

    #[ORM\Column(name: 'rate_key', type: Types::TEXT)]
    private string $rateKey = '';

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'flouci_payment_id', length: 100, nullable: true)]
    private ?string $flouciPaymentId = null;

    #[ORM\Column(name: 'hotelbeds_reference', length: 60, nullable: true)]
    private ?string $hotelbedsReference = null;

    #[ORM\Column(name: 'user_id', nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->reference = 'EVEC-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    public function getId(): ?int { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function getHotelCode(): string { return $this->hotelCode; }
    public function setHotelCode(string $v): self { $this->hotelCode = $v; return $this; }
    public function getHotelName(): string { return $this->hotelName; }
    public function setHotelName(string $v): self { $this->hotelName = $v; return $this; }
    public function getRoomName(): ?string { return $this->roomName; }
    public function setRoomName(?string $v): self { $this->roomName = $v; return $this; }
    public function getBoard(): ?string { return $this->board; }
    public function setBoard(?string $v): self { $this->board = $v; return $this; }
    public function getCheckIn(): ?\DateTimeInterface { return $this->checkIn; }
    public function setCheckIn(?\DateTimeInterface $v): self { $this->checkIn = $v; return $this; }
    public function getCheckOut(): ?\DateTimeInterface { return $this->checkOut; }
    public function setCheckOut(?\DateTimeInterface $v): self { $this->checkOut = $v; return $this; }
    public function getNights(): int { return $this->nights; }
    public function setNights(int $v): self { $this->nights = $v; return $this; }
    public function getAdults(): int { return $this->adults; }
    public function setAdults(int $v): self { $this->adults = $v; return $this; }
    public function getChildren(): int { return $this->children; }
    public function setChildren(int $v): self { $this->children = $v; return $this; }
    public function getRooms(): int { return $this->rooms; }
    public function setRooms(int $v): self { $this->rooms = $v; return $this; }
    public function getHolderName(): string { return $this->holderName; }
    public function setHolderName(string $v): self { $this->holderName = $v; return $this; }
    public function getHolderSurname(): string { return $this->holderSurname; }
    public function setHolderSurname(string $v): self { $this->holderSurname = $v; return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $v): self { $this->email = $v; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): self { $this->phone = $v; return $this; }
    public function getAmountEur(): float { return $this->amountEur; }
    public function setAmountEur(float $v): self { $this->amountEur = $v; return $this; }
    public function getAmountTnd(): float { return $this->amountTnd; }
    public function setAmountTnd(float $v): self { $this->amountTnd = $v; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $v): self { $this->currency = $v; return $this; }
    public function getRateKey(): string { return $this->rateKey; }
    public function setRateKey(string $v): self { $this->rateKey = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }
    public function getFlouciPaymentId(): ?string { return $this->flouciPaymentId; }
    public function setFlouciPaymentId(?string $v): self { $this->flouciPaymentId = $v; return $this; }
    public function getHotelbedsReference(): ?string { return $this->hotelbedsReference; }
    public function setHotelbedsReference(?string $v): self { $this->hotelbedsReference = $v; return $this; }
    public function getUserId(): ?int { return $this->userId; }
    public function setUserId(?int $v): self { $this->userId = $v; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
}
