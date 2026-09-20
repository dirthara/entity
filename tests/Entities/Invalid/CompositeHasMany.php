<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasMany;
use Dirthara\Entity\Tests\Entities\Comment;

#[Entity(table: 'composite_has_many')]
final class CompositeHasMany
{
    #[Id]
    public int $teamId;

    #[Id]
    public int $userId;

    #[HasMany(target: Comment::class)]
    public array $comments;
}
