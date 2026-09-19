<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasMany;

#[Entity]
final class MissingHasManyTarget
{
    #[Id]
    public int $id;

    /**
     * @var array<int, object>
     */
    #[HasMany(target: 'Dirthara\Entity\Tests\Entities\Missing')]
    public array $things;
}
