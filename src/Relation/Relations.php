<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation;

final readonly class Relations
{
    public function __construct(
        public RelationLoader $loader,
        public RelationHandleFactory $handles,
        public RelationStateRegistry $states,
    ) {}
}
