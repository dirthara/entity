<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Tests\Doubles\Converters;

#[Entity]
final class ConverterFactoryReturn
{
    #[Id]
    public int $id;

    #[Column(converter: Converters::nothing(...))]
    public string $name;
}
