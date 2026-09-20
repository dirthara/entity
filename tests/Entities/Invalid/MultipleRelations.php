<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasOne;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Entity\Tests\Entities\Country;

#[Entity]
final class MultipleRelations
{
    #[Id]
    public int $id;

    #[HasOne]
    #[BelongsToOne]
    public Country $country;
}
