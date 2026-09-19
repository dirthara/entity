<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\Ignore;
use Dirthara\Entity\Attribute\Generated;

#[Entity]
final class Profile
{
    public static string $shared = 'not a column';

    #[Id]
    #[Generated]
    public int $id;

    #[Column(name: 'display_name')]
    public string $displayName;

    #[Column(converter: 'json')]
    public array $meta;

    public Role $role;

    public ?string $bio;

    #[Ignore]
    public string $transient;
}
