<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Tests\Entities\Writer;
use Dirthara\Entity\Attribute\BelongsToOne;

#[Entity(table: 'topics')]
final class UncapturedForeignKey
{
    #[Id]
    #[Generated]
    public int $id;

    public string $name;

    #[BelongsToOne]
    public ?Writer $writer;
}
