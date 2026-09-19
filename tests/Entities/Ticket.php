<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Tests\Doubles\Converters;
use Dirthara\Entity\Tests\Doubles\UppercaseConverter;

#[Entity(table: 'tickets')]
final class Ticket
{
    #[Id]
    #[Generated]
    public int $id;

    #[Column(converter: UppercaseConverter::class)]
    public string $code;

    #[Column(converter: Converters::uppercase(...))]
    public string $label;

    #[Column(converter: Converters::configured(...))]
    public string $reference;
}
