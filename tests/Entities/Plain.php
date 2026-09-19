<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;

final class Plain
{
    #[Id]
    public int $id;

    public string $name;
}
