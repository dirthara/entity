<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;

#[Entity]
final class Person implements Owner
{
    #[Id]
    public int $id;

    public string $name;
}
