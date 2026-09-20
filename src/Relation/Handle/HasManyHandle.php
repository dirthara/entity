<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation\Handle;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\HasManyMetadata;
use Dirthara\Entity\Metadata\RelationMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Database\Exceptions\DatabaseException;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Entity\Exception\PersistenceException;
use Dirthara\Entity\Exception\EntityDatabaseException;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Exception\RelationLoadingException;
use Dirthara\Entity\Exception\InvalidIdentifierException;

final readonly class HasManyHandle implements RelationHandle
{
    public function __construct(
        private ConnectedDatabase $database,
        private EntityMetadata $metadata,
        private EntityMetadata $target,
        private HasManyMetadata $relation,
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
    public function add(object $related): void
    {
        $owner = $this->identity->owner(metadata: $this->metadata, entity: $this->entity);

        $this->write(foreignKey: $owner, related: $related, onlyOwned: false);
    }

    /**
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     * @throws EntityDatabaseException
     * @throws RelationLoadingException
     */
    public function remove(object $related): void
    {
        $this->write(foreignKey: null, related: $related, onlyOwned: true);
    }

    /**
     * @throws InvalidIdentifierException
     * @throws MappingException
     * @throws PersistenceException
     * @throws TypeConversionException
     */
    private function write(string|int|float|bool|null $foreignKey, object $related, bool $onlyOwned): void
    {
        $this->identity->assertTarget(relation: $this->relation, entity: $this->metadata->entity, related: $related);

        $identifier = $this->identity->related(
            target: $this->target,
            related: $related,
            entity: $this->metadata->entity,
            relation: $this->relation->property,
        );

        $query = $this->database->table($this->target->table)->where(
            $this->target->identifier->single()->column(),
            ComparisonOperator::Equal,
            $identifier,
        );

        if ($onlyOwned) {
            $query->where(
                $this->relation->foreignKey,
                ComparisonOperator::Equal,
                $this->identity->owner(metadata: $this->metadata, entity: $this->entity),
            );
        }

        try {
            $query->update([$this->relation->foreignKey => $foreignKey]);
        } catch (DatabaseException $exception) {
            throw PersistenceException::relationWriteFailed(
                entity: $this->metadata->entity,
                relation: $this->relation->property,
                previous: $exception,
            );
        }

        $this->refresher->refresh(
            database: $this->database,
            metadata: $this->metadata,
            entity: $this->entity,
            relation: $this->relation->property,
        );
    }
}
