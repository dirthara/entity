<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\HasOne;
use Dirthara\Entity\Attribute\HasMany;
use Dirthara\Entity\Attribute\BelongsToOne;
use Dirthara\Entity\Attribute\BelongsToMany;
use Dirthara\Entity\Relation\RelationLoading;

#[Entity(table: 'drafts')]
final class Draft
{
    #[Id]
    public int $id;

    #[BelongsToOne(foreignKey: 'home', loading: RelationLoading::Eager)]
    public Country $country;

    #[BelongsToOne(target: Person::class)]
    public Owner $owner;

    #[HasOne(foreignKey: 'draft_key', loading: RelationLoading::Eager)]
    public Profile $profile;

    #[HasOne(target: Person::class)]
    public Owner $keeper;

    #[HasMany(target: Comment::class, foreignKey: 'draft_key', loading: RelationLoading::Eager)]
    public array $comments;

    #[BelongsToMany(
        target: Tag::class,
        table: 'draft_labels',
        foreignKey: 'draft_key',
        relatedForeignKey: 'label_key',
        loading: RelationLoading::Eager,
    )]
    public iterable $labels;
}
