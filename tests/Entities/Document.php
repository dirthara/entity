<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;

/**
 * A bare `array` property with no converter named, so it falls back to `json`.
 */
#[Entity]
final class Document
{
    #[Id]
    public int $id;

    public array $payload;
}
