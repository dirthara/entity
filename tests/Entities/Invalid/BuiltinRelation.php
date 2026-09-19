<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\BelongsToOne;

#[Entity]
final class BuiltinRelation
{
    #[Id]
    public int $id;

    #[BelongsToOne]
    public string $country;
}
