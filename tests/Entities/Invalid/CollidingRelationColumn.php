<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Entity\Tests\Entities\Country;

#[Entity]
final class CollidingRelationColumn
{
    #[Id]
    public int $id;

    #[Column(name: 'country_code')]
    public string $code;

    #[BelongsToOne]
    public Country $country;
}
