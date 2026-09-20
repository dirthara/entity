<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Attribute\BelongsToOne;

#[Entity(table: 'reviews')]
final class PlainReview
{
    #[Id]
    #[Generated]
    public int $id;

    public string $body;

    #[BelongsToOne(target: EagerBook::class)]
    public EagerBook $book;
}
