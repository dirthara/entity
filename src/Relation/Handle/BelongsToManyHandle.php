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
use Dirthara\Entity\Exception\EntityDatabaseException;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Exception\RelationLoadingException;
use Dirthara\Entity\Exception\InvalidIdentifierException;

final readonly class BelongsToManyHandle implements RelationHandle
{
    public function __construct(
        private ConnectedDatabase $database,
        private EntityMetadata $metadata,
        private EntityMetadata $target,
        private BelongsToManyMetadata $relation,
        private object $entity,
        private RelationRefresher $refresher,
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
     * @throws EntityDatabaseException
     * @throws RelationLoadingException
     */
    public function attach(object $related): void
    {
        $owner = $this->identity->owner(metadata: $this->metadata, entity: $this->entity);
        $identifier = $this->identifierOf($related);

        $attached = $this->run(function () use ($owner, $identifier): bool {
            if (in_array($identifier, $this->attached($owner), strict: true)) {
                return false;
            }

            $this->insert($owner, [$identifier]);

            return true;
        });

        if (!$attached) {
            return;
        }

        $this->refresher->refresh(
            database: $this->database,
            metadata: $this->metadata,
            entity: $this->entity,
            relation: $this->relation->property,
        );
    }

    /**
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     * @throws EntityDatabaseException
     * @throws RelationLoadingException
     */
    public function detach(object $related): void
    {
        $owner = $this->identity->owner(metadata: $this->metadata, entity: $this->entity);
        $identifier = $this->identifierOf($related);

        $this->run(function () use ($owner, $identifier): void {
            $this->delete($owner, [$identifier]);
        });

        $this->refresher->refresh(
            database: $this->database,
            metadata: $this->metadata,
            entity: $this->entity,
            relation: $this->relation->property,
        );
    }

    /**
     * @param iterable<object> $related
     *
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     * @throws EntityDatabaseException
     * @throws RelationLoadingException
     */
    public function sync(iterable $related): void
    {
        $owner = $this->identity->owner(metadata: $this->metadata, entity: $this->entity);

        $wanted = [];

        foreach ($related as $item) {
            $wanted[] = $this->identifierOf($item);
        }

        $this->run(fn(): mixed => $this->database->transaction(function () use ($owner, $wanted): void {
            $current = $this->attached($owner);

            $this->delete($owner, array_values(array_diff($current, $wanted)));
            $this->insert($owner, array_values(array_diff($wanted, $current)));
        }));

        $this->refresher->refresh(
            database: $this->database,
            metadata: $this->metadata,
            entity: $this->entity,
            relation: $this->relation->property,
        );
    }

    /**
     * @param list<string|int|float|bool> $identifiers
     */
    private function delete(string|int|float|bool $owner, array $identifiers): void
    {
        if ($identifiers === []) {
            return;
        }

        $this->database
            ->table($this->relation->table)
            ->where($this->relation->foreignKey, ComparisonOperator::Equal, $owner)
            ->whereIn($this->relation->relatedForeignKey, $identifiers)
            ->delete();
    }

    /**
     * @param list<string|int|float|bool> $identifiers
     */
    private function insert(string|int|float|bool $owner, array $identifiers): void
    {
        if ($identifiers === []) {
            return;
        }

        $rows = array_map(fn(string|int|float|bool $identifier): array => [
            $this->relation->foreignKey => $owner,
            $this->relation->relatedForeignKey => $identifier,
        ], $identifiers);

        $this->database->table($this->relation->table)->insert($rows);
    }

    /**
     * @return list<string|int|float|bool>
     */
    private function attached(string|int|float|bool $owner): array
    {
        $rows = $this->database
            ->table($this->relation->table)
            ->where($this->relation->foreignKey, ComparisonOperator::Equal, $owner)
            ->get();

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
