<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation\Handle;

use Dirthara\Entity\Metadata\RelationMetadata;

interface RelationHandle
{
    public function relation(): RelationMetadata;
}
