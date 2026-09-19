<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Tests\Doubles\Money;
use Dirthara\Entity\Tests\Doubles\MoneyConverter;

#[Entity]
final class CompositeCollidesWithColumn
{
    #[Id]
    public int $id;

    #[Column(converter: MoneyConverter::class)]
    public Money $price;

    #[Column(name: 'price_currency')]
    public string $label;
}
