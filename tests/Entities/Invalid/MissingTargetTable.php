<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasMany;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Tests\Entities\Plain;
use Dirthara\Collection\Contract\Collection;

/**
 * A to-many pointing at an entity whose table is not there.
 */
#[Entity(table: 'books')]
final class MissingTargetTable
{
    #[Id]
    #[Generated]
    public int $id;

    public string $title;

    /**
     * @var Collection<int, Plain>
     */
    #[HasMany(target: Plain::class, foreignKey: 'book_id')]
    public Collection $rows;
}
