<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use DateTimeImmutable;
use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;

final class FractionalSecondsOnDate
{
    #[Id]
    public int $id;

    #[Column(converter: 'date', fractionalSeconds: 3)]
    public DateTimeImmutable $value;
}
