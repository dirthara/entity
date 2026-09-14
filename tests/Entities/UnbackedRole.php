<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

/**
 * An enum with no backing type, so there is no value a column could hold.
 */
enum UnbackedRole
{
    case Admin;
    case Member;
}
