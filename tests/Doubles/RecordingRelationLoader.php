<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Relation\RelationLoader;

/**
 * Records how the query batches its entities before handing them over.
 */
final class RecordingRelationLoader implements RelationLoader
{
    /**
     * @var list<int>
     */
    public array $batches = [];

    /**
     * @var list<list<string>>
     */
    public array $relations = [];

    public function load(ConnectedDatabase $database, EntityMetadata $metadata, array $entities, array $relations): void
    {
        $this->batches[] = count($entities);
        $this->relations[] = $relations;
    }
}
