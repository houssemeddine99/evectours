<?php

namespace App\Repository;

use App\Entity\VoyageImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VoyageImage>
 */
class VoyageImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VoyageImage::class);
    }

    /**
     * Find all images for a specific voyage
     *
     * @return VoyageImage[]
     */
   public function findByVoyageId(int $voyageId): array
    {
        $images = $this->findBy(['voyageId' => $voyageId]);
        
        if (empty($images)) {
            return $this->getDefaultImages();
        }
        
        return $images;
    }

    /**
     * Get default images when no voyage images are found
     */
private function getDefaultImages(): array
{
    $defaultImage = new VoyageImage();
    $defaultImage->setVoyageId(0); // or null if your entity allows
    $defaultImage->setImageUrl('https://cratertravelagencies.com/assets/img/crater5.jpg');
    $defaultImage->setCloudinaryPublicId('default');
    $defaultImage->setCreatedAt(new \DateTime());
    $defaultImage->setUpdatedAt(new \DateTime());

    return [$defaultImage];
}

    /**
     * Batch-load images for multiple voyages in a single query.
     *
     * @param int[] $ids
     * @return array<int, VoyageImage[]>  voyageId => VoyageImage[]
     */
    public function findImagesByVoyageIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        /** @var VoyageImage[] $images */
        $images = $this->createQueryBuilder('vi')
            ->where('vi.voyageId IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($images as $img) {
            $map[$img->getVoyageId()][] = $img;
        }

        return $map;
    }
}