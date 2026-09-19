<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

use Dirthara\Entity\Relation\RelationLoading;

abstract readonly class RelationMetadata
{
    public function __construct(
        public string $property,
        public string $target,
        public RelationLoading $loading,
    ) {}
}
