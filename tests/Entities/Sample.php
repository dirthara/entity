<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use DateTimeImmutable;
use Dirthara\Entity\Attribute\Id;

final class Sample
{
    #[Id(fractionalSeconds: 6)]
    public DateTimeImmutable $takenAt;
}
