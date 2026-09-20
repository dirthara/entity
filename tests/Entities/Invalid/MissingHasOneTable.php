<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasOne;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Tests\Entities\Plain;

#[Entity(table: 'books')]
final class MissingHasOneTable
{
    #[Id]
    #[Generated]
    public int $id;

    public string $title;

    #[HasOne]
    public ?Plain $row;
}
