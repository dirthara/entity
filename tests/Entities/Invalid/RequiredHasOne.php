<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasOne;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Tests\Entities\Jacket;

#[Entity(table: 'books')]
final class RequiredHasOne
{
    #[Id]
    #[Generated]
    public int $id;

    public string $title;

    #[HasOne(foreignKey: 'book_id')]
    public Jacket $jacket;
}
