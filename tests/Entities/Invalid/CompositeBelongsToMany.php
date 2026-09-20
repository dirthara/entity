<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Tests\Entities\Tag;
use Dirthara\Entity\Attribute\BelongsToMany;

#[Entity(table: 'composite_belongs_to_many')]
final class CompositeBelongsToMany
{
    #[Id]
    public int $teamId;

    #[Id]
    public int $userId;

    #[BelongsToMany(target: Tag::class)]
    public array $tags;
}
