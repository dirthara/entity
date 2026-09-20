<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Tests\Entities\Topic;
use Dirthara\Collection\Contract\Collection;
use Dirthara\Entity\Attribute\BelongsToMany;

#[Entity(table: 'books')]
final class MissingPivotTable
{
    #[Id]
    #[Generated]
    public int $id;

    public string $title;

    /**
     * @var Collection<int, Topic>
     */
    #[BelongsToMany(target: Topic::class, table: 'nope')]
    public Collection $topics;
}
