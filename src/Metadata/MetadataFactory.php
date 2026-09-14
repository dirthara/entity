<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

use ReflectionType;
use ReflectionClass;
use ReflectionProperty;
use ReflectionException;
use ReflectionNamedType;
use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\Ignore;
use Dirthara\Entity\Type\TypeRegistry;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Naming\NamingStrategy;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Exception\TypeConversionException;

final readonly class MetadataFactory
{
    public function __construct(
        private NamingStrategy $naming,
        private TypeRegistry $types,
    ) {}

    /**
     * @template T of object
     *
     * @param class-string<T> $entity
     *
     * @return EntityMetadata<T>
     *
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function create(string $entity): EntityMetadata
    {
        try {
            $reflection = new ReflectionClass($entity);
        } catch (ReflectionException $exception) {
            throw MappingException::fromReflection($exception, $entity);
        }

        $this->validateEntity($reflection);

        $entityAttribute = $this->entityAttribute($reflection);

        $table = $entityAttribute->table ?? $this->naming->table($reflection->getShortName());

        $connection = $entityAttribute?->connection;

        /** @var array<string, PropertyMetadata> $properties */
        $properties = [];

        /** @var list<PropertyMetadata> $identifiers */
        $identifiers = [];

        /** @var array<string, string> $columns */
        $columns = [];

        foreach ($this->properties($reflection) as $property) {
            $metadata = $this->propertyMetadata(entity: $entity, property: $property);

            if ($metadata === null) {
                continue;
            }

            if (isset($columns[$metadata->column])) {
                throw MappingException::duplicateColumn(
                    entity: $entity,
                    column: $metadata->column,
                    firstProperty: $columns[$metadata->column],
                    secondProperty: $metadata->property,
                );
            }

            $properties[$metadata->property] = $metadata;
            $columns[$metadata->column] = $metadata->property;

            if ($metadata->identifier) {
                $identifiers[] = $metadata;
            }
        }

        if ($identifiers === []) {
            throw MappingException::missingIdentifier($entity);
        }

        return new EntityMetadata(
            entity: $entity,
            table: $table,
            identifier: new IdentifierMetadata(properties: $identifiers),
            properties: $properties,
            connection: $connection,
        );
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @throws MappingException
     */
    private function validateEntity(ReflectionClass $reflection): void
    {
        match (true) {
            $reflection->isInterface() => throw MappingException::invalidEntityType(
                $reflection->getName(),
                'interface',
            ),
            $reflection->isAbstract() => throw MappingException::invalidEntityType($reflection->getName(), 'abstract'),
            $reflection->isEnum() => throw MappingException::invalidEntityType($reflection->getName(), 'enum'),
            $reflection->isTrait() => throw MappingException::invalidEntityType($reflection->getName(), 'trait'),
            default => null,
        };
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function entityAttribute(ReflectionClass $reflection): ?Entity
    {
        $attribute = $reflection->getAttributes(Entity::class)[0] ?? null;

        // @mago-expect lint:inline-variable-return
        /** @var Entity|null $entity */
        $entity = $attribute?->newInstance();

        return $entity;
    }

    /**
     * @return list<ReflectionProperty>
     */
    private function properties(ReflectionClass $reflection): array
    {
        return array_values(array_filter(
            $reflection->getProperties(),
            static fn(ReflectionProperty $property): bool => !$property->isStatic(),
        ));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $entity
     *
     * @throws MappingException
     * @throws TypeConversionException
     */
    private function propertyMetadata(string $entity, ReflectionProperty $property): ?PropertyMetadata
    {
        $ignore = $this->hasAttribute($property, Ignore::class);
        $id = $this->attribute($property, Id::class);
        $column = $this->attribute($property, Column::class);
        $generated = $this->hasAttribute($property, Generated::class);

        if ($ignore) {
            $this->validateIgnoredProperty(
                entity: $entity,
                property: $property,
                hasId: $id !== null,
                hasColumn: $column !== null,
                generated: $generated,
            );

            return null;
        }

        if ($id !== null && $column !== null) {
            throw MappingException::conflictingAttributes(
                entity: $entity,
                property: $property->getName(),
                attributes: [Id::class, Column::class],
            );
        }

        $type = $this->propertyType(entity: $entity, property: $property);

        $mapping = $id ?? $column;

        $converterType = $mapping->converter ?? $type->getName();

        $converter = $this->types->resolve(propertyType: $type->getName(), converterType: $converterType);

        return new PropertyMetadata(
            property: $property->getName(),
            column: $mapping->column ?? $this->naming->column($property->getName()),
            propertyType: $type->getName(),
            converter: $converter,
            nullable: $type->allowsNull(),
            identifier: $id !== null,
            generated: $generated,
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $entity
     *
     * @throws MappingException
     */
    private function propertyType(string $entity, ReflectionProperty $property): ReflectionNamedType
    {
        $type = $property->getType();

        if ($type === null) {
            throw MappingException::missingPropertyType(entity: $entity, property: $property->getName());
        }

        if (!$type instanceof ReflectionNamedType) {
            throw MappingException::unsupportedPropertyType(
                entity: $entity,
                property: $property->getName(),
                type: $this->typeName($type),
            );
        }

        if ($type->getName() === 'mixed') {
            throw MappingException::unsupportedPropertyType(
                entity: $entity,
                property: $property->getName(),
                type: 'mixed',
            );
        }

        return $type;
    }

    /**
     * @param class-string $entity
     *
     * @throws MappingException
     */
    private function validateIgnoredProperty(
        string $entity,
        ReflectionProperty $property,
        bool $hasId,
        bool $hasColumn,
        bool $generated,
    ): void {
        if (!$hasId && !$hasColumn && !$generated) {
            return;
        }

        throw MappingException::conflictingAttributes(entity: $entity, property: $property->getName(), attributes: [
            Ignore::class,
            ...($hasId ? [Id::class] : []),
            ...($hasColumn ? [Column::class] : []),
            ...($generated ? [Generated::class] : []),
        ]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     *
     * @return T|null
     */
    private function attribute(ReflectionProperty $property, string $attribute): ?object
    {
        $reflectionAttribute = $property->getAttributes($attribute)[0] ?? null;

        return $reflectionAttribute?->newInstance();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $attribute
     */
    private function hasAttribute(ReflectionProperty $property, string $attribute): bool
    {
        return $property->getAttributes($attribute) !== [];
    }

    private function typeName(ReflectionType $type): string
    {
        return (string) $type;
    }
}
