<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation\Handle;

use ReflectionProperty;
use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\HasOneMetadata;
use Dirthara\Entity\Metadata\RelationMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Database\Exceptions\DatabaseException;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Entity\Exception\PersistenceException;
use Dirthara\Entity\Relation\RelationStateRegistry;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Exception\InvalidIdentifierException;

final readonly class HasOneHandle implements RelationHandle
{
    public function __construct(
        private ConnectedDatabase $database,
        private EntityMetadata $metadata,
        private EntityMetadata $target,
        private HasOneMetadata $relation,
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
    public function associate(object $related): void
    {
        $this->identity->assertTarget(relation: $this->relation, entity: $this->metadata->entity, related: $related);

        $owner = $this->identity->owner(metadata: $this->metadata, entity: $this->entity);

        $identifier = $this->identity->related(
            target: $this->target,
            related: $related,
            entity: $this->metadata->entity,
            relation: $this->relation->property,
        );

        $column = $this->target->identifier->single()->column();

        $this->run(fn(): mixed => $this->database->transaction(function () use ($owner, $identifier, $column): void {
            $this->assertFree(owner: $owner, column: $column, identifier: $identifier);

            $this->releaseOthers(owner: $owner, column: $column, keep: $identifier);

            $this->database
                ->table($this->target->table)
                ->where($column, ComparisonOperator::Equal, $identifier)
                ->update([$this->relation->foreignKey => $owner]);
        }));

        $this->remember($related);
    }

    /**
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    public function dissociate(): void
    {
        if (!$this->relation->nullable) {
            throw PersistenceException::relationNotNullable(
                entity: $this->metadata->entity,
                relation: $this->relation->property,
            );
        }

        $owner = $this->identity->owner(metadata: $this->metadata, entity: $this->entity);

        $this->run(fn(): mixed => $this->database->transaction(function () use ($owner): void {
            $this->releaseAll($owner);
        }));

        $this->remember(null);
    }

    /**
     * @throws PersistenceException
     */
    private function assertFree(string|int|float|bool $owner, string $column, string|int|float|bool $identifier): void
    {
        $row = $this->database
            ->table($this->target->table)
            ->where($column, ComparisonOperator::Equal, $identifier)
            ->first();

        $held = is_array($row) ? $row[$this->relation->foreignKey] ?? null : null;

        if (!is_scalar($held) || (string) $held === (string) $owner) {
            return;
        }

        throw PersistenceException::relationTaken(
            entity: $this->metadata->entity,
            relation: $this->relation->property,
            target: $this->target->entity,
            owner: $held,
        );
    }

    /**
     * @throws PersistenceException
     */
    private function releaseAll(string|int|float|bool $owner): void
    {
        $this->assertReleasedOne(
            $this->database
                ->table($this->target->table)
                ->where($this->relation->foreignKey, ComparisonOperator::Equal, $owner)
                ->update([$this->relation->foreignKey => null]),
        );
    }

    /**
     * @throws PersistenceException
     */
    private function releaseOthers(string|int|float|bool $owner, string $column, mixed $keep): void
    {
        $this->assertReleasedOne(
            $this->database
                ->table($this->target->table)
                ->where($this->relation->foreignKey, ComparisonOperator::Equal, $owner)
                ->where($column, ComparisonOperator::NotEqual, $keep)
                ->update([$this->relation->foreignKey => null]),
        );
    }

    /**
     * @throws PersistenceException
     */
    private function assertReleasedOne(int $affected): void
    {
        if ($affected <= 1) {
            return;
        }

        throw PersistenceException::unexpectedRelatedRows(
            entity: $this->metadata->entity,
            relation: $this->relation->property,
            expectedMaximum: 1,
            actual: $affected,
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

    private function remember(?object $related): void
    {
        new ReflectionProperty($this->entity, $this->relation->property)->setRawValue($this->entity, $related);

        $this->states->markLoaded(entity: $this->entity, relation: $this->relation->property);
    }
}
