<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Doubles;

use Dirthara\Entity\Metadata\RelationMetadata;

/**
 * A relation kind the loader has no branch for.
 */
final readonly class UnknownRelation extends RelationMetadata {}
