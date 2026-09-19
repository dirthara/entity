<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Tests\Doubles\Money;
use Dirthara\Entity\Tests\Doubles\MoneyConverter;

#[Entity]
final class NamedColumnsEmpty
{
    #[Id]
    public int $id;

    #[Column(name: ['amount' => 'price_amount', 'currency' => ''], converter: MoneyConverter::class)]
    public Money $price;
}
