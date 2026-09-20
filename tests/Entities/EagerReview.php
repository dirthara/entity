<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Entity\Relation\RelationLoading;

#[Entity(table: 'reviews')]
final class EagerReview
{
    #[Id]
    #[Generated]
    public int $id;

    public string $body;

    #[BelongsToOne(loading: RelationLoading::Eager, target: EagerBook::class)]
    public EagerBook $book;
}
