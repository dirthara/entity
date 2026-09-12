<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

final readonly class PropertyMetadata
{
    public function __construct(
        public string $name,
        public string $storageName,
        public string $type,
        public string $storageType,
        public bool $nullable,
    ) {}
}
