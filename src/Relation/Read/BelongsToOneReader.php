<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation\Read;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Metadata\BelongsToOneMetadata;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Exception\RelationLoadingException;

final readonly class BelongsToOneReader
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
    public function read(ConnectedDatabase $database, BelongsToOneMetadata $relation, array $entities): array
    {
        $target = $this->rows->targetOf($relation);
        $identifier = $this->rows->singleIdentifier($target);

        /**
         * @var array<array-key, string|int|float|bool> $foreignKeys
         */
        $foreignKeys = [];

        /**
         * @var array<array-key, list<object>> $owners
         */
        $owners = [];

        foreach ($entities as $entity) {
            $foreignKey = $this->rows->capturedForeignKey(entity: $entity, relation: $relation);

            if ($foreignKey === null) {
                if (!$relation->nullable) {
                    throw RelationLoadingException::nullRelationNotAllowed(
                        entity: $entity::class,
                        relation: $relation->property,
                    );
                }

                $this->rows->assign(entity: $entity, relation: $relation, value: null);

                continue;
            }

            $key = $this->rows->key($foreignKey);

            $foreignKeys[$key] = $this->rows->scalar($foreignKey);
            $owners[$key][] = $entity;
        }

        if ($foreignKeys === []) {
            return [];
        }

        $related = [];

        foreach ($database
            ->table($target->table)
            ->whereIn($identifier->column(), array_values($foreignKeys))
            ->get() as $row) {
            $value = $this->rows->rowValue(
                row: $row,
                column: $identifier->column(),
                entity: $target->entity,
                relation: $relation->property,
            );

            $related[$this->rows->key($value)] = $this->rows->hydrate(metadata: $target, row: $row);
        }

        $loaded = [];

        foreach ($owners as $key => $entities) {
            if (!isset($related[$key])) {
                throw RelationLoadingException::relatedEntityNotFound(
                    entity: $relation->target,
                    relation: $relation->property,
                    identifier: $key,
                );
            }

            $loaded[] = $related[$key];

            foreach ($entities as $entity) {
                $this->rows->assign(entity: $entity, relation: $relation, value: $related[$key]);
            }
        }

        return $loaded;
    }
}
