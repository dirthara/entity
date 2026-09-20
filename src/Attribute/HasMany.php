<?php

declare(strict_types=1);

namespace Dirthara\Entity\Attribute;

use Attribute;
use Dirthara\Entity\Relation\RelationLoading;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class HasMany
{
    /**
     * @param class-string $target
     */
    public function __construct(
        public string $target,
        public ?string $foreignKey = null,
        public RelationLoading $loading = RelationLoading::Explicit,
    ) {}
}
