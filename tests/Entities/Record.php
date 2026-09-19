<?php

declare(strict_types=1);

namespace Dirthara\Entity\Tests\Entities;

use Dirthara\Entity\Attribute\Id;
use Dirthara\Entity\Attribute\Column;
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\Generated;

/**
 * One column of every kind the package converts, for the conformance suite.
 */
#[Entity(table: 'conformance_records')]
final class Record
{
    #[Id]
    #[Generated]
    public int $id;

    #[Column(name: 'display_name')]
    public string $displayName;

    public bool $active;

    public float $score;

    #[Column(converter: 'json')]
    public array $meta;

    public Role $role;

    public ?string $note;
}
