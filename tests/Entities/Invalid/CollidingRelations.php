<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Entity\Tests\Entities\Country;

#[Entity]
final class CollidingRelations
{
    #[Id]
    public int $id;

    #[BelongsToOne(foreignKey: 'owner_id')]
    public Country $home;

    #[BelongsToOne(foreignKey: 'owner_id')]
    public Country $away;
}
