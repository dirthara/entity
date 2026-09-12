<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

use Dirthara\Entity\Type\TypeConverter;

final readonly class PropertyMetadata
{
    public function __construct(
        public string $property,
        public string $column,
        public string $propertyType,
        public TypeConverter $converter,
        public bool $nullable,
        public bool $identifier,
        public bool $generated,
    ) {}
}
