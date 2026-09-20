<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation\Read;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Collection\ImmutableCollection;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Metadata\HasManyMetadata;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Exception\TypeConversionException;
use Dirthara\Entity\Exception\RelationLoadingException;

final readonly class HasManyReader
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
        HasManyMetadata $relation,
        array $entities,
    ): array {
        $target = $this->rows->targetOf($relation);

        [$identifiers, $owners] = $this->rows->groupByIdentifier(metadata: $metadata, entities: $entities);

        /**
         * @var array<array-key, list<object>> $related
         */
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

            $related[$this->rows->key($foreignKey)][] = $this->rows->hydrate(metadata: $target, row: $row);
        }

        $loaded = [];

        foreach ($owners as $key => $entities) {
            $items = $related[$key] ?? [];

            $loaded = [...$loaded, ...$items];

            $collection = new ImmutableCollection($items);

            foreach ($entities as $entity) {
                $this->rows->assign(entity: $entity, relation: $relation, value: $collection);
            }
        }

        return $loaded;
    }
}
