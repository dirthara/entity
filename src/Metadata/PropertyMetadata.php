<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

final readonly class PropertyMetadata
{
    public function __construct(
        public string $name,
        public string $column,
        public string $type,
        public string $columnType,
        public bool $nullable,
    ) {}
}
