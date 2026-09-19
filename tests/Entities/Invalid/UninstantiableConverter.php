<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Tests\Doubles\AbstractConverter;

#[Entity]
final class UninstantiableConverter
{
    #[Id]
    public int $id;

    #[Column(converter: AbstractConverter::class)]
    public string $name;
}
