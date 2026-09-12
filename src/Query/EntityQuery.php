<?php

declare(strict_types=1);

namespace Dirthara\Entity\Query;

use Closure;
use Dirthara\Collection\Collection;
use Dirthara\Entity\Type\TypeRegistry;
use Dirthara\Entity\Hydration\Hydrator;
use Dirthara\Database\Query\QueryBuilder;
use Dirthara\Collection\ImmutableCollection;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Database\Query\Sql\OrderDirection;
use Dirthara\Database\Exceptions\DatabaseException;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Entity\Exceptions\CreateEntityException;
use Dirthara\Entity\Exceptions\EntityMappingException;
use Dirthara\Entity\Exceptions\EntityDatabaseException;
use Dirthara\Entity\Exceptions\TypeConversionException;

/**
 * @template T of object
 */
final readonly class EntityQuery
{
    /**
     * @param EntityMetadata<T> $metadata
     */
    public function __construct(
        private EntityMetadata $metadata,
        private Hydrator $hydrator,
        private TypeRegistry $types,
        private QueryBuilder $builder,
    ) {}

    /**
     * @throws EntityMappingException
     * @throws TypeConversionException
     */
    public function where(string $property, ComparisonOperator|string $operator, mixed $value): self
    {
        $metadata = $this->property($property);

        $this->builder->where($metadata->column, $operator, $this->toDatabase($metadata, $value));

        return $this;
    }

    /**
     * @throws EntityMappingException
     * @throws TypeConversionException
     */
    public function orWhere(string $property, ComparisonOperator|string $operator, mixed $value): self
    {
        $metadata = $this->property($property);

        $this->builder->orWhere($metadata->column, $operator, $this->toDatabase($metadata, $value));

        return $this;
    }

    /**
     * @throws EntityMappingException
     */
    public function whereNull(string $property): self
    {
        $metadata = $this->property($property);

        $this->builder->whereNull($metadata->column);

        return $this;
    }

    /**
     * @throws EntityMappingException
     */
    public function whereNotNull(string $property): self
    {
        $metadata = $this->property($property);

        $this->builder->whereNotNull($metadata->column);

        return $this;
    }

    /**
     * @param iterable<mixed> $values
     *
     * @throws EntityMappingException
     * @throws TypeConversionException
     */
    public function whereIn(string $property, iterable $values): self
    {
        $metadata = $this->property($property);

        $this->builder->whereIn($metadata->column, $this->convertValues($metadata, $values));

        return $this;
    }

    /**
     * @param iterable<mixed> $values
     *
     * @throws EntityMappingException
     * @throws TypeConversionException
     */
    public function whereNotIn(string $property, iterable $values): self
    {
        $metadata = $this->property($property);

        $this->builder->whereNotIn($metadata->column, $this->convertValues($metadata, $values));

        return $this;
    }

    /**
     * @throws EntityMappingException
     * @throws TypeConversionException
     */
    public function whereBetween(string $property, mixed $from, mixed $to): self
    {
        $metadata = $this->property($property);

        $this->builder->whereBetween(
            $metadata->column,
            $this->toDatabase($metadata, $from),
            $this->toDatabase($metadata, $to),
        );

        return $this;
    }

    public function whereNested(Closure $callback): self
    {
        $this->builder->whereNested(function (QueryBuilder $builder) use ($callback): void {
            $query = new self(
                metadata: $this->metadata,
                hydrator: $this->hydrator,
                types: $this->types,
                builder: $builder,
            );

            $callback($query);
        });

        return $this;
    }

    /**
     * @throws EntityMappingException
     */
    public function orderBy(string $property, OrderDirection $direction = OrderDirection::Ascending): self
    {
        $metadata = $this->property($property);

        $this->builder->orderBy($metadata->column, $direction);

        return $this;
    }

    /**
     * @throws EntityMappingException
     */
    public function orderByDesc(string $property): self
    {
        return $this->orderBy($property, OrderDirection::Descending);
    }

    public function limit(int $limit): self
    {
        $this->builder->limit($limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->builder->offset($offset);

        return $this;
    }

    /**
     * @return Collection<T>
     *
     * @throws CreateEntityException
     * @throws EntityDatabaseException
     */
    public function get(): Collection
    {
        $entities = [];

        try {
            foreach ($this->builder->get() as $row) {
                $entity = $this->hydrator->newInstance($this->metadata);

                $this->hydrator->hydrate($this->metadata, $entity, $row);

                $entities[] = $entity;
            }
        } catch (DatabaseException $exception) {
            throw EntityDatabaseException::fromDatabaseException($exception);
        }

        return new ImmutableCollection($entities);
    }

    /**
     * @return T|null
     *
     * @throws EntityDatabaseException
     * @throws CreateEntityException
     */
    public function first(): ?object
    {
        try {
            $row = $this->builder->first();
        } catch (DatabaseException $exception) {
            throw EntityDatabaseException::fromDatabaseException($exception);
        }

        if ($row === null) {
            return null;
        }

        $entity = $this->hydrator->newInstance($this->metadata);

        $this->hydrator->hydrate($this->metadata, $entity, $row);

        return $entity;
    }

    /**
     * @return iterable<T>
     *
     * @throws EntityDatabaseException
     * @throws CreateEntityException
     */
    public function cursor(): iterable
    {
        try {
            foreach ($this->builder->cursor() as $row) {
                $entity = $this->hydrator->newInstance($this->metadata);

                $this->hydrator->hydrate($this->metadata, $entity, $row);

                yield $entity;
            }
        } catch (DatabaseException $exception) {
            throw EntityDatabaseException::fromDatabaseException($exception);
        }
    }

    /**
     * @throws EntityDatabaseException
     */
    public function exists(): bool
    {
        try {
            return $this->builder->exists();
        } catch (DatabaseException $exception) {
            throw EntityDatabaseException::fromDatabaseException($exception);
        }
    }

    /**
     * @throws EntityDatabaseException
     */
    public function count(): int
    {
        try {
            return $this->builder->count();
        } catch (DatabaseException $exception) {
            throw EntityDatabaseException::fromDatabaseException($exception);
        }
    }

    /**
     * @throws EntityMappingException
     */
    private function property(string $property): PropertyMetadata
    {
        return $this->metadata->property($property);
    }

    /**
     * @throws TypeConversionException
     */
    private function toDatabase(PropertyMetadata $property, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $typeConverter = $this->types->get($property->propertyType);

        return $typeConverter->toDatabase($value);
    }

    /**
     * @param iterable<mixed> $values
     *
     * @return list<mixed>
     *
     * @throws TypeConversionException
     */
    private function convertValues(PropertyMetadata $property, iterable $values): array
    {
        $converted = [];

        foreach ($values as $value) {
            $converted[] = $this->toDatabase($property, $value);
        }

        return $converted;
    }
}
