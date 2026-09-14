<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;

#[Entity(table: 'memberships')]
final class Membership
{
    #[Id]
    public int $teamId;

    #[Id]
    public int $userId;

    public string $role;
}
