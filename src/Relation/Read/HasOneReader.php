<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation\Read;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\HasOneMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Exception\RelationLoadingException;

final readonly class HasOneReader
{
    public function __construct(
        private RelatedRows $rows,
    ) {}

    /**
     * @param list<object> $entities
     *
     * @return list<object>
     *
     * @throws RelationLoadingException
     * @throws MappingException
     * @throws TypeConversionException
     */
    public function read(
        ConnectedDatabase $database,
        EntityMetadata $metadata,
        HasOneMetadata $relation,
        array $entities,
    ): array {
        $target = $this->rows->targetOf($relation);

        [$identifiers, $owners] = $this->rows->groupByIdentifier(metadata: $metadata, entities: $entities);

        $related = [];

        foreach ($database
            ->table($target->table)
            ->whereIn($relation->foreignKey, array_values($identifiers))
            ->get() as $row) {
            $foreignKey = $this->rows->rowValue(
                row: $row,
                column: $relation->foreignKey,
                entity: $target->entity,
                relation: $relation->property,
            );

            $key = $this->rows->key($foreignKey);

            if (isset($related[$key])) {
                throw RelationLoadingException::multipleRelatedEntities(
                    entity: $metadata->entity,
                    relation: $relation->property,
                    identifier: $key,
                );
            }

            $related[$key] = $this->rows->hydrate(metadata: $target, row: $row);
        }

        $loaded = [];

        foreach ($owners as $key => $entities) {
            $value = $related[$key] ?? null;

            if ($value === null && !$relation->nullable) {
                throw RelationLoadingException::relatedEntityNotFound(
                    entity: $relation->target,
                    relation: $relation->property,
                    identifier: $key,
                );
            }

            if ($value !== null) {
                $loaded[] = $value;
            }

            foreach ($entities as $entity) {
                $this->rows->assign(entity: $entity, relation: $relation, value: $value);
            }
        }

        return $loaded;
    }
}
