<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation;

enum RelationLoading
{
    case Eager;
    case Explicit;
}
