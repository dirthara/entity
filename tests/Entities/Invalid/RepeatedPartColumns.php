<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Tests\Doubles\NamelessPartsConverter;

#[Entity]
final class RepeatedPartColumns
{
    #[Id]
    public int $id;

    #[Column(converter: NamelessPartsConverter::class)]
    public ?string $thing;
}
