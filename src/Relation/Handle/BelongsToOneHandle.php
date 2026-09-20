<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation\Handle;

use ReflectionProperty;
use ReflectionException;
use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\RelationMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Metadata\BelongsToOneMetadata;
use Dirthara\Database\Exceptions\DatabaseException;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Entity\Exception\PersistenceException;
use Dirthara\Entity\Relation\RelationStateRegistry;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Exception\InvalidIdentifierException;

final readonly class BelongsToOneHandle implements RelationHandle
{
    public function __construct(
        private ConnectedDatabase $database,
        private EntityMetadata $metadata,
        private EntityMetadata $target,
        private BelongsToOneMetadata $relation,
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
     * @throws ReflectionException
     */
    public function associate(object $related): void
    {
        $this->identity->assertTarget(relation: $this->relation, entity: $this->metadata->entity, related: $related);

        $this->write($this->identity->related(
            target: $this->target,
            related: $related,
            entity: $this->metadata->entity,
            relation: $this->relation->property,
        ));

        $this->remember($related);
    }

    /**
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     * @throws ReflectionException
     */
    public function dissociate(): void
    {
        if (!$this->relation->nullable) {
            throw PersistenceException::relationNotNullable(
                entity: $this->metadata->entity,
                relation: $this->relation->property,
            );
        }

        $this->write(null);

        $this->remember(null);
    }

    /**
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    private function write(string|int|float|bool|null $foreignKey): void
    {
        $owner = $this->identity->owner(metadata: $this->metadata, entity: $this->entity);

        try {
            $this->database
                ->table($this->metadata->table)
                ->where($this->metadata->identifier->single()->column(), ComparisonOperator::Equal, $owner)
                ->update([$this->relation->foreignKey => $foreignKey]);
        } catch (DatabaseException $exception) {
            throw PersistenceException::relationWriteFailed(
                entity: $this->metadata->entity,
                relation: $this->relation->property,
                previous: $exception,
            );
        }
    }

    /**
     * @throws ReflectionException
     */
    private function remember(?object $related): void
    {
        new ReflectionProperty($this->entity, $this->relation->property)->setRawValue($this->entity, $related);

        $this->states->markLoaded(entity: $this->entity, relation: $this->relation->property);
    }
}
