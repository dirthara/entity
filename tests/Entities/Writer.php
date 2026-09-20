<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasMany;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Collection\Contract\Collection;

#[Entity]
final class Writer
{
    #[Id]
    #[Generated]
    public int $id;

    public string $name;

    /**
     * @var Collection<int, Book>
     */
    #[HasMany(target: Book::class)]
    public Collection $books;
}
