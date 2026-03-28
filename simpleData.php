<?php

require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use App\Entity\User;
use App\Entity\Voyage;
use App\Entity\Offer;
use App\Entity\Activity;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$entityManager = $container->get('doctrine')->getManager();

// Clear existing data
$entityManager->createQuery('DELETE FROM App\Entity\Offer')->execute();
$entityManager->createQuery('DELETE FROM App\Entity\Activity')->execute();
$entityManager->createQuery('DELETE FROM App\Entity\Voyage')->execute();
$entityManager->createQuery('DELETE FROM App\Entity\User')->execute();

$images = [
    'https://activetravel.com.tn/public/images/image/voyage-alacarte-bali_0.34075400-1670336947.jpg',
    'https://image.urlaubspiraten.de/4x3/image/upload/v1650989387/mediavault_images/AdobeStock_315088533_dhq3mv.jpg',
    'https://www.voyagetunisie.tn/images/voyages-tunisie.png',
    'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQQSZrm9BDjRa-qgqTCSPMs9sw_juRRd9Gonw&s'
];

// Create users
$users = [];
for ($i = 1; $i <= 3; $i++) {
    $user = new User();
    $user->setUsername('user' . $i);
    $user->setEmail('user' . $i . '@example.com');
    $user->setPassword(password_hash('password' . $i, PASSWORD_DEFAULT));
    $user->setTel('12345678' . $i);
    $user->setImageUrl($images[array_rand($images)]);
    $user->setCreatedAt(new \DateTime());
    $entityManager->persist($user);
    $users[] = $user;
}

// Create voyages
$voyages = [];
$destinations = ['Bali, Indonesia', 'Tunisia', 'Paris, France', 'Tokyo, Japan'];
$descriptions = [
    'Experience the beauty of Bali with its beaches and culture.',
    'Discover the wonders of Tunisia with its history and landscapes.',
    'Explore the romantic city of Paris.',
    'Dive into the vibrant culture of Tokyo.'
];

for ($i = 0; $i < 4; $i++) {
    $voyage = new Voyage();
    $voyage->setTitle('Voyage to ' . explode(',', $destinations[$i])[0]);
    $voyage->setDescription($descriptions[$i]);
    $voyage->setDestination($destinations[$i]);
    $voyage->setStartDate(new \DateTime('2024-06-01'));
    $voyage->setEndDate(new \DateTime('2024-06-10'));
    $voyage->setPrice((string)rand(500, 2000));
    // Set multiple images
    $voyageImages = [$images[$i % count($images)]];
    if ($i < 2) { // Add extra images for first two voyages
        $voyageImages[] = $images[($i + 1) % count($images)];
    }
    $voyage->setImageUrl($voyageImages);
    $voyage->setCreatedAt(new \DateTime());
    $entityManager->persist($voyage);
    $voyages[] = $voyage;
}

// Create offers
$offerTitles = ['Early Bird Discount', 'Family Package', 'Last Minute Deal', 'Group Discount'];
$discounts = ['15.00', '20.00', '25.00', '30.00'];
foreach ($voyages as $voyage) {
    for ($k = 0; $k < 2; $k++) {  // 2 offers per voyage
        $offer = new Offer();
        $offer->setVoyage($voyage);
        $offer->setTitle($offerTitles[$k] . ' for ' . $voyage->getTitle());
        $offer->setDescription('Special ' . strtolower($offerTitles[$k]) . ' with great savings.');
        $offer->setDiscountPercentage($discounts[$k]);
        $offer->setStartDate(new \DateTime('2024-05-01'));
        $offer->setEndDate(new \DateTime('2024-05-31'));
        $offer->setIsActive(true);
        $entityManager->persist($offer);
    }
}

// Create activities
$activityNames = ['Beach Day', 'Cultural Tour', 'Hiking', 'City Exploration'];
foreach ($voyages as $voyage) {
    for ($j = 0; $j < 2; $j++) {
        $activity = new Activity();
        $activity->setVoyage($voyage);
        $activity->setName($activityNames[$j]);
        $activity->setDescription('Enjoy a ' . strtolower($activityNames[$j]) . ' in ' . $voyage->getDestination());
        $activity->setDurationHours(rand(2, 8));
        $activity->setPricePerPerson((string)rand(50, 200));
        $entityManager->persist($activity);
    }
}

$entityManager->flush();

echo "Sample data inserted successfully!\n";