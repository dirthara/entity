<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\BelongsToMany;
use Dirthara\Entity\Tests\Entities\Membership;

#[Entity]
final class CompositeManyTarget
{
    #[Id]
    public int $id;

    #[BelongsToMany(target: Membership::class)]
    public array $memberships;
}
