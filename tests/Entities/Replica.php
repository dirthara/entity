<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;

#[Entity(table: 'replicas', connection: 'replica')]
final class Replica
{
    #[Id]
    public int $id;
}
