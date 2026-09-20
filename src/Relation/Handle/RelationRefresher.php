<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation\Handle;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Relation\RelationLoader;
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Relation\RelationStateRegistry;
use Dirthara\Entity\Exception\EntityDatabaseException;
use Dirthara\Entity\Exception\RelationLoadingException;

final readonly class RelationRefresher
{
    public function __construct(
        private RelationLoader $loader,
        private RelationStateRegistry $states,
    ) {}

    /**
     * @throws EntityDatabaseException
     * @throws MappingException
     * @throws RelationLoadingException
     */
    public function refresh(
        ConnectedDatabase $database,
        EntityMetadata $metadata,
        object $entity,
        string $relation,
    ): void {
        $loaded = $this->states->isLoaded(entity: $entity, relation: $relation);

        $this->states->markUnloaded(entity: $entity, relation: $relation);

        if (!$loaded) {
            return;
        }

        $this->loader->load(database: $database, metadata: $metadata, entities: [$entity], relations: [$relation]);
    }
}
