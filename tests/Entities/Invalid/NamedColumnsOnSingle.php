<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities\Invalid;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;

#[Entity]
final class NamedColumnsOnSingle
{
    #[Id]
    public int $id;

    #[Column(name: ['a' => 'b'])]
    public string $title;
}
