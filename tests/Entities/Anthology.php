<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasMany;
use Dirthara\Entity\Attribute\Generated;

/**
 * A to-many kept in a plain array rather than a collection.
 */
#[Entity(table: 'books')]
final class Anthology
{
    #[Id]
    #[Generated]
    public int $id;

    public string $title;

    /**
     * @var array<int, Chapter>
     */
    #[HasMany(target: Chapter::class, foreignKey: 'book_id')]
    public array $chapters;
}
