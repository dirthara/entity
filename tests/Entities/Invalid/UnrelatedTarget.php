<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Tests\Entities\Tag;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Entity\Tests\Entities\Country;

#[Entity]
final class UnrelatedTarget
{
    #[Id]
    public int $id;

    #[BelongsToOne(target: Tag::class)]
    public Country $country;
}
