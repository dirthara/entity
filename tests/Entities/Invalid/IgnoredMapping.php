<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\Ignore;
use Dirthara\Entity\Attribute\Generated;

#[Entity]
final class IgnoredMapping
{
    #[Ignore]
    #[Id]
    #[Column]
    #[Generated]
    public int $id;
}
