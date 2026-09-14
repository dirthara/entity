<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;

#[Entity]
final class ConflictingAttributes
{
    #[Id]
    #[Column]
    public int $id;
}
