<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;

/**
 * No `#[Entity]` attribute at all: the table comes from the naming strategy and
 * the entity uses the default connection.
 */
final class Plain
{
    #[Id]
    public int $id;

    public string $name;
}
