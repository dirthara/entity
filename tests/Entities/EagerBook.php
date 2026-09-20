<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasMany;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Collection\Contract\Collection;
use Dirthara\Entity\Relation\RelationLoading;

#[Entity(table: 'books')]
final class EagerBook
{
    #[Id]
    #[Generated]
    public int $id;

    public string $title;

    #[BelongsToOne(loading: RelationLoading::Eager)]
    public Writer $writer;

    /**
     * @var Collection<int, Chapter>
     */
    #[HasMany(target: Chapter::class, foreignKey: 'book_id')]
    public Collection $chapters;
}
