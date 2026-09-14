<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

use Dirthara\Entity\Exception\InvalidIdentifierException;

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
     * @throws InvalidIdentifierException
     */
    public function single(): PropertyMetadata
    {
        if (!$this->isSingle()) {
            throw InvalidIdentifierException::identifierIsComposite(array_map(
                static fn(PropertyMetadata $property): string => $property->property,
                $this->properties,
            ));
        }

        return $this->properties[0];
    }
}
