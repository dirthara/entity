<?php

declare(strict_types=1);

namespace Dirthara\Entity\Query;

use Closure;
use Generator;
use Dirthara\Entity\Hydration\Hydrator;
use Dirthara\Database\ConnectedDatabase;
use Dirthara\Database\Query\QueryBuilder;
use Dirthara\Collection\Contract\Collection;
use Dirthara\Collection\ImmutableCollection;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Relation\RelationLoader;
use Dirthara\Entity\Relation\RelationLoading;
use Dirthara\Entity\Metadata\PropertyMetadata;
use Dirthara\Database\Query\Sql\OrderDirection;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Exception\HydrationException;
use Dirthara\Database\Exceptions\DatabaseException;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Entity\Relation\RelationStateRegistry;
use Dirthara\Entity\Exception\CreateEntityException;
use Dirthara\Entity\Exception\EntityDatabaseException;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Exception\RelationLoadingException;

/**
 * @template T of object
 */
final class EntityQuery
{
    /**
     * @var array<string, true>
     */
    private array $with = [];

    /**
     * @param EntityMetadata<T> $metadata
     */
    public function __construct(
        private readonly EntityMetadata $metadata,
        private readonly Hydrator $hydrator,
        private readonly QueryBuilder $builder,
        private readonly RelationLoader $relationLoader,
        private readonly ConnectedDatabase $database,
        private readonly RelationStateRegistry $relationStates,
    ) {}

    /**
     * @throws MappingException
     */
    public function where(string $property, ComparisonOperator|string $operator, mixed $value): self
    {
        $metadata = $this->property($property);

        $this->builder->where($metadata->column(), $operator, $this->toDatabase($metadata, $value));

        return $this;
    }

    /**
     * @throws MappingException
     */
    public function orWhere(string $property, ComparisonOperator|string $operator, mixed $value): self
    {
        $metadata = $this->property($property);

        $this->builder->orWhere($metadata->column(), $operator, $this->toDatabase($metadata, $value));

        return $this;
    }

    /**
     * @throws MappingException
     */
    public function whereNull(string $property): self
    {
        $metadata = $this->property($property);

        $this->builder->whereNull($metadata->column());

        return $this;
    }

    /**
     * @throws MappingException
     */
    public function whereNotNull(string $property): self
    {
        $metadata = $this->property($property);

        $this->builder->whereNotNull($metadata->column());

        return $this;
    }

    /**
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function whereIn(string $property, iterable $values): self
    {
        $metadata = $this->property($property);

        $this->builder->whereIn($metadata->column(), $this->convertValues($metadata, $values));

        return $this;
    }

    /**
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function whereNotIn(string $property, iterable $values): self
    {
        $metadata = $this->property($property);

        $this->builder->whereNotIn($metadata->column(), $this->convertValues($metadata, $values));

        return $this;
    }

    /**
     * @throws MappingException
     */
    public function whereBetween(string $property, mixed $from, mixed $to): self
    {
        $metadata = $this->property($property);

        $this->builder->whereBetween(
            $metadata->column(),
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
                builder: $builder,
                relationLoader: $this->relationLoader,
                database: $this->database,
                relationStates: $this->relationStates,
            );

            $callback($query);
        });

        return $this;
    }

    /**
     * @throws MappingException
     */
    public function orderBy(string $property, OrderDirection $direction = OrderDirection::Ascending): self
    {
        $metadata = $this->property($property);

        $this->builder->orderBy($metadata->column(), $direction);

        return $this;
    }

    /**
     * @throws MappingException
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
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function with(string ...$relations): self
    {
        $this->relationLoader->assertLoadable(metadata: $this->metadata, relations: array_values($relations));

        foreach ($relations as $relation) {
            $this->with[$relation] = true;
        }

        return $this;
    }

    /**
     * @return Collection<int, T>
     *
     * @throws CreateEntityException
     * @throws EntityDatabaseException
     * @throws TypeConversionException
     * @throws HydrationException
     * @throws RelationLoadingException
     */
    public function get(): Collection
    {
        $entities = [];

        try {
            foreach ($this->builder->get() as $row) {
                $entity = $this->hydrator->newInstance($this->metadata);

                $entities[] = $entity;

                $this->hydrator->hydrate($this->metadata, $entity, $row);

                $this->relationStates->capture(metadata: $this->metadata, entity: $entity, row: $row);
            }

            $this->loadRelations($entities);
        } catch (DatabaseException $exception) {
            throw EntityDatabaseException::fromDatabaseException(
                exception: $exception,
                entity: $this->metadata->entity,
                operation: 'get',
            );
        }

        return new ImmutableCollection($entities);
    }

    /**
     * @return T|null
     *
     * @throws EntityDatabaseException
     * @throws CreateEntityException
     * @throws TypeConversionException
     * @throws HydrationException
     * @throws RelationLoadingException
     */
    public function first(): ?object
    {
        try {
            $row = $this->builder->first();
        } catch (DatabaseException $exception) {
            throw EntityDatabaseException::fromDatabaseException(
                exception: $exception,
                entity: $this->metadata->entity,
                operation: 'first',
            );
        }

        if ($row === null) {
            return null;
        }

        $entity = $this->hydrator->newInstance($this->metadata);

        $this->hydrator->hydrate($this->metadata, $entity, $row);

        $this->relationStates->capture(metadata: $this->metadata, entity: $entity, row: $row);

        $this->loadRelations([$entity]);

        return $entity;
    }

    /**
     * @return iterable<T>
     *
     * @throws EntityDatabaseException
     * @throws CreateEntityException
     * @throws TypeConversionException
     * @throws HydrationException
     * @throws RelationLoadingException
     */
    public function cursor(): iterable
    {
        $relations = $this->relationsToLoad();

        if ($relations !== []) {
            throw RelationLoadingException::cursorCannotLoadRelations(
                entity: $this->metadata->entity,
                relations: $relations,
            );
        }

        return $this->stream();
    }

    /**
     * @return Generator<int, T>
     *
     * @throws EntityDatabaseException
     * @throws CreateEntityException
     * @throws TypeConversionException
     * @throws HydrationException
     * @throws RelationLoadingException
     */
    private function stream(): Generator
    {
        try {
            foreach ($this->builder->cursor() as $row) {
                $entity = $this->hydrator->newInstance($this->metadata);

                $this->hydrator->hydrate($this->metadata, $entity, $row);

                $this->relationStates->capture(metadata: $this->metadata, entity: $entity, row: $row);

                yield $entity;
            }
        } catch (DatabaseException $exception) {
            throw EntityDatabaseException::fromDatabaseException(
                exception: $exception,
                entity: $this->metadata->entity,
                operation: 'cursor',
            );
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
            throw EntityDatabaseException::fromDatabaseException(
                exception: $exception,
                entity: $this->metadata->entity,
                operation: 'exists',
            );
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
            throw EntityDatabaseException::fromDatabaseException(
                exception: $exception,
                entity: $this->metadata->entity,
                operation: 'count',
            );
        }
    }

    /**
     * @throws MappingException
     */
    private function property(string $property): PropertyMetadata
    {
        return $this->metadata->property($property);
    }

    /**
     * @throws MappingException
     */
    private function toDatabase(PropertyMetadata $property, mixed $value): string|int|float|bool|null
    {
        if ($value === null) {
            return null;
        }

        return $property->single()->toDatabase($value);
    }

    /**
     * @return list<string|int|float|bool>
     *
     * @throws TypeConversionException
     * @throws MappingException
     */
    private function convertValues(PropertyMetadata $property, iterable $values): array
    {
        $converted = [];

        foreach ($values as $value) {
            $bound = $property->single()->toDatabase($value);

            if ($bound === null) {
                throw TypeConversionException::invalidValue(expected: 'a value IN can match', actual: $value);
            }

            $converted[] = $bound;
        }

        return $converted;
    }

    /**
     * @return list<string>
     */
    private function relationsToLoad(): array
    {
        $relations = $this->with;

        foreach ($this->metadata->relations as $relation) {
            if ($relation->loading !== RelationLoading::Eager) {
                continue;
            }

            $relations[$relation->property] = true;
        }

        return array_keys($relations);
    }

    /**
     * @param list<object> $entities
     */
    private function loadRelations(array $entities): void
    {
        if ($entities === []) {
            return;
        }

        $relations = $this->relationsToLoad();

        if ($relations === []) {
            return;
        }

        $this->relationLoader->load(
            database: $this->database,
            metadata: $this->metadata,
            entities: $entities,
            relations: $relations,
        );
    }
}
