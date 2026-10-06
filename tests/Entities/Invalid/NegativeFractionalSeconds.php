<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use DateTimeImmutable;
use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;

final class NegativeFractionalSeconds
{
    #[Id]
    public int $id;

    #[Column(fractionalSeconds: -1)]
    public DateTimeImmutable $value;
}
