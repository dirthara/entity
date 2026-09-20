<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation\Handle;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\RelationMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Database\Exceptions\DatabaseException;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Entity\Exception\PersistenceException;
use Dirthara\Entity\Metadata\BelongsToManyMetadata;
use Dirthara\Entity\Relation\RelationStateRegistry;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Exception\InvalidIdentifierException;

final readonly class BelongsToManyHandle implements RelationHandle
{
    public function __construct(
        private ConnectedDatabase $database,
        private EntityMetadata $metadata,
        private EntityMetadata $target,
        private BelongsToManyMetadata $relation,
        private object $entity,
        private RelationStateRegistry $states,
        private RelationIdentity $identity,
    ) {}

    public function relation(): RelationMetadata
    {
        return $this->relation;
    }

    /**
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function attach(object $related): void
    {
        $owner = $this->identity->owner(metadata: $this->metadata, entity: $this->entity);
        $identifier = $this->identifierOf($related);

        if (in_array($identifier, $this->attached($owner), strict: true)) {
            return;
        }

        $this->run(fn(): int => $this->database
            ->table($this->relation->table)
            ->insert([
                $this->relation->foreignKey => $owner,
                $this->relation->relatedForeignKey => $identifier,
            ]));

        $this->states->markUnloaded(entity: $this->entity, relation: $this->relation->property);
    }

    /**
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function detach(object $related): void
    {
        $owner = $this->identity->owner(metadata: $this->metadata, entity: $this->entity);
        $identifier = $this->identifierOf($related);

        $this->run(
            fn(): int => $this->database
                ->table($this->relation->table)
                ->where($this->relation->foreignKey, ComparisonOperator::Equal, $owner)
                ->where($this->relation->relatedForeignKey, ComparisonOperator::Equal, $identifier)
                ->delete(),
        );

        $this->states->markUnloaded(entity: $this->entity, relation: $this->relation->property);
    }

    /**
     * @param iterable<object> $related
     *
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function sync(iterable $related): void
    {
        $owner = $this->identity->owner(metadata: $this->metadata, entity: $this->entity);

        $wanted = [];

        foreach ($related as $item) {
            $wanted[] = $this->identifierOf($item);
        }

        $current = $this->attached($owner);

        array_diff($current, $wanted) |> array_values(...) |> (fn($x) => $this->detachAll($owner, $x));

        array_diff($wanted, $current) |> array_values(...) |> (fn($x) => $this->attachAll($owner, $x));

        $this->states->markUnloaded(entity: $this->entity, relation: $this->relation->property);
    }

    /**
     * @param list<string|int|float|bool> $identifiers
     *
     * @throws PersistenceException
     */
    private function detachAll(string|int|float|bool $owner, array $identifiers): void
    {
        if ($identifiers === []) {
            return;
        }

        $this->run(
            fn(): int => $this->database
                ->table($this->relation->table)
                ->where($this->relation->foreignKey, ComparisonOperator::Equal, $owner)
                ->whereIn($this->relation->relatedForeignKey, $identifiers)
                ->delete(),
        );
    }

    /**
     * @param list<string|int|float|bool> $identifiers
     *
     * @throws PersistenceException
     */
    private function attachAll(string|int|float|bool $owner, array $identifiers): void
    {
        if ($identifiers === []) {
            return;
        }

        $rows = array_map(fn(string|int|float|bool $identifier): array => [
            $this->relation->foreignKey => $owner,
            $this->relation->relatedForeignKey => $identifier,
        ], $identifiers);

        $this->run(fn(): int => $this->database->table($this->relation->table)->insert($rows));
    }

    /**
     * @return list<string|int|float|bool>
     *
     * @throws PersistenceException
     */
    private function attached(string|int|float|bool $owner): array
    {
        $rows = $this->run(
            fn(): array => $this->database
                ->table($this->relation->table)
                ->where($this->relation->foreignKey, ComparisonOperator::Equal, $owner)
                ->get(),
        );

        $identifiers = [];

        foreach ($rows as $row) {
            $identifier = $row[$this->relation->relatedForeignKey] ?? null;

            if (is_string($identifier) || is_int($identifier) || is_float($identifier) || is_bool($identifier)) {
                $identifiers[] = $identifier;
            }
        }

        return $identifiers;
    }

    /**
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    private function identifierOf(object $related): string|int|float|bool
    {
        $this->identity->assertTarget(relation: $this->relation, entity: $this->metadata->entity, related: $related);

        return $this->identity->related(
            target: $this->target,
            related: $related,
            entity: $this->metadata->entity,
            relation: $this->relation->property,
        );
    }

    /**
     * @template TResult
     *
     * @param callable(): TResult $operation
     *
     * @return TResult
     *
     * @throws PersistenceException
     */
    private function run(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (DatabaseException $exception) {
            throw PersistenceException::relationWriteFailed(
                entity: $this->metadata->entity,
                relation: $this->relation->property,
                previous: $exception,
            );
        }
    }
}
