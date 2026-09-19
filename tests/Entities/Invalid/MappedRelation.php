<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\Ignore;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Entity\Tests\Entities\Country;

#[Entity]
final class MappedRelation
{
    #[Id]
    public int $id;

    #[BelongsToOne]
    #[Id]
    #[Column]
    #[Generated]
    #[Ignore]
    public Country $country;
}
