<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasMany;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Collection\Contract\Collection;
use Dirthara\Entity\Relation\RelationLoading;

#[Entity(table: 'books')]
final class CyclicBook
{
    #[Id]
    #[Generated]
    public int $id;

    public string $title;

    /**
     * @var Collection<int, CyclicChapter>
     */
    #[HasMany(target: CyclicChapter::class, foreignKey: 'book_id', loading: RelationLoading::Eager)]
    public Collection $chapters;
}
