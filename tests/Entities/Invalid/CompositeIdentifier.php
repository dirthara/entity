<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Tests\Doubles\Money;
use Dirthara\Entity\Tests\Doubles\MoneyConverter;

#[Entity]
final class CompositeIdentifier
{
    #[Id(converter: MoneyConverter::class)]
    public Money $price;
}
