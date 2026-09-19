<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\BelongsToMany;

/**
 * A many to many an entity points at itself, with both keys told apart.
 */
#[Entity]
final class Category
{
    #[Id]
    public int $id;

    public string $name;

    #[BelongsToMany(
        target: self::class,
        table: 'category_relations',
        foreignKey: 'category_id',
        relatedForeignKey: 'related_category_id',
    )]
    public array $related;
}
