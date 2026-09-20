<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

use Dirthara\Entity\Relation\RelationLoading;

final readonly class BelongsToOneMetadata extends RelationMetadata
{
    /**
     * @param class-string $target
     */
    public function __construct(
        string $property,
        string $target,
        RelationLoading $loading,
        public string $foreignKey,
        public bool $nullable,
        public PropertyMetadata $targetIdentifier,
    ) {
        parent::__construct(property: $property, target: $target, loading: $loading);
    }
}
