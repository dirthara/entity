<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Entity\Tests\Entities\Membership;

#[Entity]
final class CompositeRelationTarget
{
    #[Id]
    public int $id;

    #[BelongsToOne]
    public Membership $membership;
}
