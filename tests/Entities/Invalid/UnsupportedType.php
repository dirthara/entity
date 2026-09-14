<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use DateTimeImmutable;
use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;

#[Entity]
final class UnsupportedType
{
    #[Id]
    public int $id;

    public DateTimeImmutable $createdAt;
}
