<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation\Read;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Collection\ImmutableCollection;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Metadata\BelongsToManyMetadata;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Exception\RelationLoadingException;

final readonly class BelongsToManyReader
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
        BelongsToManyMetadata $relation,
        array $entities,
    ): array {
        $target = $this->rows->targetOf($relation);
        $identifier = $this->rows->singleIdentifier($target);

        [$identifiers, $owners] = $this->rows->groupByIdentifier(metadata: $metadata, entities: $entities);

        /**
         * @var array<array-key, list<array-key>> $relatedKeys
         */
        $relatedKeys = [];

        /**
         * @var array<array-key, string|int|float|bool> $relatedIdentifiers
         */
        $relatedIdentifiers = [];

        foreach ($database
            ->table($relation->table)
            ->whereIn($relation->foreignKey, array_values($identifiers))
            ->get() as $row) {
            $ownerIdentifier = $this->rows->rowValue(
                row: $row,
                column: $relation->foreignKey,
                entity: $metadata->entity,
                relation: $relation->property,
            );

            $relatedIdentifier = $this->rows->rowValue(
                row: $row,
                column: $relation->relatedForeignKey,
                entity: $relation->target,
                relation: $relation->property,
            );

            $relatedKey = $this->rows->key($relatedIdentifier);

            $relatedKeys[$this->rows->key($ownerIdentifier)][] = $relatedKey;
            $relatedIdentifiers[$relatedKey] = $this->rows->scalar($relatedIdentifier);
        }

        $related = $this->targets(
            database: $database,
            target: $target,
            relation: $relation,
            column: $identifier->column(),
            identifiers: $relatedIdentifiers,
        );

        $loaded = [];

        foreach ($owners as $ownerKey => $entities) {
            $items = [];

            foreach ($relatedKeys[$ownerKey] ?? [] as $relatedKey) {
                if (!isset($related[$relatedKey])) {
                    throw RelationLoadingException::relatedEntityNotFound(
                        entity: $relation->target,
                        relation: $relation->property,
                        identifier: $relatedKey,
                    );
                }

                $items[] = $related[$relatedKey];
            }

            $loaded = [...$loaded, ...$items];

            $collection = new ImmutableCollection($items);

            foreach ($entities as $entity) {
                $this->rows->assign(entity: $entity, relation: $relation, value: $collection);
            }
        }

        return $this->rows->distinct($loaded);
    }

    /**
     * @param array<array-key, string|int|float|bool> $identifiers
     *
     * @return array<array-key, object>
     *
     * @throws RelationLoadingException
     * @throws MappingException
     * @throws TypeConversionException
     */
    private function targets(
        ConnectedDatabase $database,
        EntityMetadata $target,
        BelongsToManyMetadata $relation,
        string $column,
        array $identifiers,
    ): array {
        if ($identifiers === []) {
            return [];
        }

        $related = [];

        foreach ($database->table($target->table)->whereIn($column, array_values($identifiers))->get() as $row) {
            $value = $this->rows->rowValue(
                row: $row,
                column: $column,
                entity: $target->entity,
                relation: $relation->property,
            );

            $related[$this->rows->key($value)] = $this->rows->hydrate(metadata: $target, row: $row);
        }

        return $related;
    }
}
