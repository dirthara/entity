<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use DateTime;
use DateTimeImmutable;
use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\Generated;

#[Entity(table: 'conformance_readings')]
final class Reading
{
    #[Id]
    #[Generated]
    public int $id;

    #[Column(fractionalSeconds: 3)]
    public DateTimeImmutable $loggedAt;

    #[Column(converter: 'timestamp', fractionalSeconds: 6)]
    public DateTime $measuredAt;

    #[Column(converter: 'time', fractionalSeconds: 3)]
    public DateTimeImmutable $opensAt;

    #[Column(fractionalSeconds: 0)]
    public DateTimeImmutable $settledAt;

    #[Column(fractionalSeconds: 3)]
    public DateTimeImmutable $sampledAt;
}
