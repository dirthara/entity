<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;

#[Entity(table: 'links')]
final class Link
{
    #[Id]
    public int $fromId;

    #[Id]
    public int $toId;
}
