<?php

namespace App\Service;

use App\Entity\Tag;
use App\Entity\Voyage;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;

class TagService
{
    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /** @return array<int, array{id: int, name: string, color: ?string}> */
    public function getAllTags(): array
    {
        return array_map(
            fn(Tag $t) => ['id' => $t->getId(), 'name' => $t->getName(), 'color' => $t->getColor()],
            $this->tagRepository->findBy([], ['name' => 'ASC'])
        );
    }

    public function createTag(string $name, ?string $color = null): Tag
    {
        $tag = new Tag();
        $tag->setName(trim($name));
        $tag->setColor($color);
        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        return $tag;
    }

    public function deleteTag(int $id): bool
    {
        $tag = $this->tagRepository->find($id);
        if (!$tag) {
            return false;
        }
        $this->entityManager->remove($tag);
        $this->entityManager->flush();
        return true;
    }

    /** @param int[] $tagIds */
    public function syncVoyageTags(Voyage $voyage, array $tagIds): void
    {
        foreach ($voyage->getTags()->toArray() as $tag) {
            $voyage->removeTag($tag);
        }
        foreach ($tagIds as $tagId) {
            $tag = $this->tagRepository->find((int) $tagId);
            if ($tag) {
                $voyage->addTag($tag);
            }
        }
        $this->entityManager->flush();
    }
}
