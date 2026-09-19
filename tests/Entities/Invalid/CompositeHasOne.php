<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasOne;
use Dirthara\Entity\Tests\Entities\Profile;

#[Entity(table: 'composite_has_one')]
final class CompositeHasOne
{
    #[Id]
    public int $teamId;

    #[Id]
    public int $userId;

    #[HasOne]
    public Profile $profile;
}
