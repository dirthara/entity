<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Database\ConnectedDatabase;
use Dirthara\Entity\Metadata\EntityMetadata;
use Dirthara\Entity\Relation\RelationLoader;

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

    public function assertLoadable(EntityMetadata $metadata, array $relations): void {}

    public function load(
        ConnectedDatabase $database,
        EntityMetadata $metadata,
        array $entities,
        array $relations,
        array $without = [],
    ): void {
        $this->batches[] = count($entities);
        $this->relations[] = $relations;
    }
}
