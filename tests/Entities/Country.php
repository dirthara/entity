<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;

#[Entity]
final class Country
{
    #[Id]
    public string $code;

    public string $name;
}
