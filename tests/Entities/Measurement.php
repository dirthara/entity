<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;

/**
 * A nullable column whose type is not a string.
 */
#[Entity]
final class Measurement
{
    #[Id]
    public int $id;

    public ?int $celsius;
}
