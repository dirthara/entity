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

        $this->write(foreignKey: $owner, column: $this->target->identifier->single()->column(), value: $identifier);

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

        $this->write(foreignKey: null, column: $this->relation->foreignKey, value: $owner);

        $this->remember(null);
    }

    /**
     * @throws PersistenceException
     */
    private function write(string|int|float|bool|null $foreignKey, string $column, mixed $value): void
    {
        try {
            $this->database
                ->table($this->target->table)
                ->where($column, ComparisonOperator::Equal, $value)
                ->update([$this->relation->foreignKey => $foreignKey]);
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
