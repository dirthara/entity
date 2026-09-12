<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

use Dirthara\Entity\Exceptions\EntityMappingException;

final readonly class EntityMetadata
{
    /**
     * @template T of object
     *
     * @param class-string<T> $entity
     * @param array<string, PropertyMetadata> $properties
     */
    public function __construct(
        public string $entity,
        public string $table,
        public IdentifierMetadata $primaryKey,
        public array $properties,
    ) {}

    /**
     * @throws EntityMappingException
     */
    public function property(string $name): PropertyMetadata
    {
        return (
            $this->properties[$name] ?? throw EntityMappingException::unknownProperty(
                entity: $this->entity,
                property: $name,
            )
        );
    }
}
