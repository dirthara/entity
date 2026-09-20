<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

use Dirthara\Entity\Exception\MappingException;

/**
 * @template T of object
 */
final readonly class EntityMetadata
{
    /**
     * @param class-string<T> $entity
     * @param array<string, PropertyMetadata> $properties
     * @param array<string, RelationMetadata> $relations
     */
    public function __construct(
        public string $entity,
        public string $table,
        public IdentifierMetadata $identifier,
        public array $properties,
        public array $relations,
        public ?string $connection = null,
    ) {}

    /**
     * @throws MappingException
     */
    public function property(string $name): PropertyMetadata
    {
        return (
            $this->properties[$name] ?? throw MappingException::unknownProperty(entity: $this->entity, property: $name)
        );
    }

    /**
     * @throws MappingException
     */
    public function relation(string $name): RelationMetadata
    {
        return (
            $this->relations[$name] ?? throw MappingException::unknownRelation(entity: $this->entity, relation: $name)
        );
    }
}
