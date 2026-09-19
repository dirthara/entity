<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Tests\Doubles\Money;
use Dirthara\Entity\Tests\Doubles\MoneyConverter;

/**
 * @see MoneyConverter
 */
#[Entity]
final class Order
{
    #[Id]
    public int $id;

    #[Column(converter: MoneyConverter::class)]
    public Money $price;

    #[Column(name: 'cost', converter: MoneyConverter::class)]
    public Money $shipping;

    #[Column(name: ['amount' => 'tax_cents', 'currency' => 'tax_ccy'], converter: MoneyConverter::class)]
    public Money $tax;
}
