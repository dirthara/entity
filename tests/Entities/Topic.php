<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Collection\Contract\Collection;
use Dirthara\Entity\Attribute\BelongsToMany;

#[Entity]
final class Topic
{
    #[Id]
    #[Generated]
    public int $id;

    public string $name;

    /**
     * @var Collection<int, Book>
     */
    #[BelongsToMany(target: Book::class)]
    public Collection $books;
}
