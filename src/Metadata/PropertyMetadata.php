<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

final readonly class PropertyMetadata
{
    public function __construct(
        public string $property,
        public string $column,
        public string $propertyType,
        public string $columnType,
        public string $converterType,
        public bool $nullable,
    ) {}
}
