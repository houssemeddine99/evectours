<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Voyage;
use PHPUnit\Framework\TestCase;

/**
 * Basic unit tests for the Voyage entity.
 */
class VoyageTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $voyage = new Voyage();

        // Title
        $voyage->setTitle('Test Voyage');
        $this->assertSame('Test Voyage', $voyage->getTitle());

        // Description
        $voyage->setDescription('A description');
        $this->assertSame('A description', $voyage->getDescription());

        // Destination
        $voyage->setDestination('Paris');
        $this->assertSame('Paris', $voyage->getDestination());

        // Dates
        $start = new \DateTime('2024-01-01');
        $end   = new \DateTime('2024-01-10');
        $voyage->setStartDate($start);
        $voyage->setEndDate($end);
        $this->assertSame($start, $voyage->getStartDate());
        $this->assertSame($end, $voyage->getEndDate());

        // Price
        $voyage->setPrice('199.99');
        $this->assertSame('199.99', $voyage->getPrice());

        // Image URL (currently not implemented, should return null)
        $this->assertNull($voyage->getImageUrl());
        $voyage->setImageUrl(['http://example.com/image.jpg']);
        // Setter does nothing, getter still null – this is the current behaviour.
        $this->assertNull($voyage->getImageUrl());
    }
}
