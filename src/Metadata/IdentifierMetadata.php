<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

use Dirthara\Entity\Exceptions\EntityMappingException;

final readonly class IdentifierMetadata
{
    /**
     * @param non-empty-list<PropertyMetadata> $properties
     */
    public function __construct(
        public array $properties,
    ) {}

    public function isComposite(): bool
    {
        return count($this->properties) > 1;
    }

    public function isSingle(): bool
    {
        return count($this->properties) === 1;
    }

    /**
     * @throws EntityMappingException
     */
    public function single(): PropertyMetadata
    {
        if (!$this->isSingle()) {
            throw new EntityMappingException('The entity has a composite identifier.');
        }

        return $this->properties[0];
    }
}
