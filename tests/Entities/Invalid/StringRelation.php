<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasMany;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Tests\Entities\Chapter;

/**
 * A to-many on a property that can hold neither a collection nor an array.
 */
#[Entity(table: 'books')]
final class StringRelation
{
    #[Id]
    #[Generated]
    public int $id;

    public string $title;

    #[HasMany(target: Chapter::class, foreignKey: 'book_id')]
    public string $chapters;
}
