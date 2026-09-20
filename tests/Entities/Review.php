<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Entity\Relation\RelationLoading;

#[Entity]
final class Review
{
    #[Id]
    #[Generated]
    public int $id;

    public string $body;

    #[BelongsToOne(loading: RelationLoading::Eager)]
    public Book $book;
}
