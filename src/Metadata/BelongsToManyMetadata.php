<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

use Dirthara\Entity\Relation\RelationLoading;

final readonly class BelongsToManyMetadata extends RelationMetadata
{
    /**
     * @param class-string $target
     */
    public function __construct(
        string $property,
        string $target,
        RelationLoading $loading,
        public string $table,
        public string $foreignKey,
        public string $relatedForeignKey,
    ) {
        parent::__construct(property: $property, target: $target, loading: $loading);
    }
}
