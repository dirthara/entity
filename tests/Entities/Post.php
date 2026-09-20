<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasOne;
use Dirthara\Entity\Attribute\HasMany;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Entity\Attribute\BelongsToMany;

#[Entity]
final class Post
{
    #[Id]
    #[Generated]
    public int $id;

    public string $title;

    #[BelongsToOne]
    public Country $country;

    #[BelongsToOne]
    public ?Account $author;

    #[HasOne]
    public ?Profile $profile;

    #[HasMany(target: Comment::class)]
    public array $comments;

    #[BelongsToMany(target: Tag::class)]
    public array $tags;
}
