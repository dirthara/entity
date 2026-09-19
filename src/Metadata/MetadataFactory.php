<?php

declare(strict_types=1);

namespace Dirthara\Entity\Metadata;

use Closure;
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
use Dirthara\Entity\Type\TypeConverter;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Naming\NamingStrategy;
use Dirthara\Entity\Type\CompositeConverter;
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

        $table = $entityAttribute?->table ?? $this->naming->table($reflection->getShortName());

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

            foreach ($metadata->columns as $column) {
                if (isset($columns[$column])) {
                    throw MappingException::duplicateColumn(
                        entity: $entity,
                        column: $column,
                        firstProperty: $columns[$column],
                        secondProperty: $metadata->property,
                    );
                }

                $columns[$column] = $metadata->property;
            }

            $properties[$metadata->property] = $metadata;

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
     * @template T of object
     *
     * @param ReflectionClass<T> $reflection
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
     * @template T of object
     *
     * @param ReflectionClass<T> $reflection
     */
    private function entityAttribute(ReflectionClass $reflection): ?Entity
    {
        $attribute = $reflection->getAttributes(Entity::class)[0] ?? null;

        return $attribute?->newInstance();
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
     * @param class-string $entity
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

        $converter = $this->converter(
            entity: $entity,
            property: $property,
            converter: $mapping?->converter,
            propertyType: $type->getName(),
        );

        if ($converter instanceof CompositeConverter) {
            $this->assertCompositeAllowed(
                entity: $entity,
                property: $property->getName(),
                identifier: $id !== null,
                generated: $generated,
            );
        }

        return new PropertyMetadata(
            property: $property->getName(),
            columns: $this->columns(
                entity: $entity,
                property: $property->getName(),
                converter: $converter,
                name: $mapping?->name,
            ),
            propertyType: $type->getName(),
            converter: $converter,
            nullable: $type->allowsNull(),
            identifier: $id !== null,
            generated: $generated,
        );
    }

    /**
     * @param class-string $entity
     * @param string|array<string, string>|null $name
     *
     * @return non-empty-array<string, string>
     *
     * @throws MappingException
     */
    private function columns(string $entity, string $property, TypeConverter $converter, string|array|null $name): array
    {
        if (!$converter instanceof CompositeConverter) {
            if (is_array($name)) {
                throw MappingException::compositeConverterNotAllowed(
                    entity: $entity,
                    property: $property,
                    because: 'its converter answers a single column',
                );
            }

            return [$property => $name ?? $this->naming->column($property)];
        }

        $parts = $converter->parts();

        if ($parts === [] || count(array_unique($parts)) !== count($parts) || in_array('', $parts, strict: true)) {
            throw MappingException::invalidConverterParts(entity: $entity, property: $property, parts: $parts);
        }

        if (is_array($name)) {
            return $this->namedColumns(entity: $entity, property: $property, parts: $parts, name: $name);
        }

        $prefix = $name ?? $property;

        return array_combine($parts, array_map(fn(string $part): string => $this->naming->column(
            $prefix . ucfirst($part),
        ), $parts));
    }

    /**
     * @param class-string $entity
     * @param non-empty-list<string> $parts
     * @param array<string, string> $name
     *
     * @return non-empty-array<string, string>
     *
     * @throws MappingException
     */
    private function namedColumns(string $entity, string $property, array $parts, array $name): array
    {
        if (!$this->namesEveryPart(parts: $parts, name: $name)) {
            throw MappingException::columnsDoNotMatchParts(
                entity: $entity,
                property: $property,
                parts: $parts,
                given: array_keys($name),
            );
        }

        return array_combine($parts, array_map(static fn(string $part): string => $name[$part], $parts));
    }

    /**
     * @param non-empty-list<string> $parts
     * @param array<string, string> $name
     */
    private function namesEveryPart(array $parts, array $name): bool
    {
        if (count($name) !== count($parts)) {
            return false;
        }

        foreach ($parts as $part) {
            $column = $name[$part] ?? null;

            if (!is_string($column) || $column === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param class-string $entity
     *
     * @throws MappingException
     */
    private function assertCompositeAllowed(string $entity, string $property, bool $identifier, bool $generated): void
    {
        if ($identifier) {
            throw MappingException::compositeConverterNotAllowed(
                entity: $entity,
                property: $property,
                because: 'it is an identifier',
            );
        }

        if ($generated) {
            throw MappingException::compositeConverterNotAllowed(
                entity: $entity,
                property: $property,
                because: 'the database generates it',
            );
        }
    }

    /**
     * @param class-string $entity
     *
     * @throws MappingException
     * @throws TypeConversionException
     */
    private function converter(
        string $entity,
        ReflectionProperty $property,
        string|Closure|null $converter,
        string $propertyType,
    ): TypeConverter {
        if ($converter instanceof Closure) {
            return $this->convertedBy(entity: $entity, property: $property, factory: $converter);
        }

        if ($converter !== null && is_a($converter, TypeConverter::class, allow_string: true)) {
            return $this->buildConverter(entity: $entity, property: $property, converter: $converter);
        }

        return $this->types->resolve(propertyType: $propertyType, converterType: $converter);
    }

    /**
     * @param class-string $entity
     * @param class-string<TypeConverter> $converter
     *
     * @throws MappingException
     */
    private function buildConverter(string $entity, ReflectionProperty $property, string $converter): TypeConverter
    {
        $reflection = new ReflectionClass($converter);

        if (!$reflection->isInstantiable()) {
            throw MappingException::unconstructableConverter(
                entity: $entity,
                property: $property->getName(),
                converter: $converter,
                reason: 'it cannot be instantiated',
            );
        }

        $constructor = $reflection->getConstructor();

        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw MappingException::unconstructableConverter(
                entity: $entity,
                property: $property->getName(),
                converter: $converter,
                reason: 'its constructor requires arguments',
            );
        }

        return $reflection->newInstance();
    }

    /**
     * @param class-string $entity
     *
     * @throws MappingException
     */
    private function convertedBy(string $entity, ReflectionProperty $property, Closure $factory): TypeConverter
    {
        $converter = $factory();

        if (!$converter instanceof TypeConverter) {
            throw MappingException::invalidConverterFactory(
                entity: $entity,
                property: $property->getName(),
                returned: get_debug_type($converter),
            );
        }

        return $converter;
    }

    /**
     * @param class-string $entity
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
     * @param class-string $attribute
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
