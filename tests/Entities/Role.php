<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

enum Role: string
{
    case Admin = 'admin';
    case Member = 'member';
}
