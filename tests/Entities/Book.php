<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasOne;
use Dirthara\Entity\Attribute\HasMany;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Collection\Contract\Collection;
use Dirthara\Entity\Attribute\BelongsToMany;

#[Entity]
final class Book
{
    #[Id]
    #[Generated]
    public int $id;

    public string $title;

    #[BelongsToOne]
    public Writer $writer;

    #[BelongsToOne]
    public ?Writer $editor;

    #[HasOne]
    public ?Jacket $jacket;

    #[HasOne]
    public ?Plate $plate;

    /**
     * @var Collection<int, Chapter>
     */
    #[HasMany(target: Chapter::class)]
    public Collection $chapters;

    /**
     * @var Collection<int, Topic>
     */
    #[BelongsToMany(target: Topic::class)]
    public Collection $topics;
}
