<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation;

use LogicException;

final class RelationState
{
    private bool $loaded = false;

    private bool $foreignKeyCaptured = false;

    private mixed $foreignKey = null;

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    public function markLoaded(): void
    {
        $this->loaded = true;
    }

    public function hasForeignKey(): bool
    {
        return $this->foreignKeyCaptured;
    }

    public function captureForeignKey(mixed $foreignKey): void
    {
        $this->foreignKey = $foreignKey;
        $this->foreignKeyCaptured = true;
    }

    public function foreignKey(): mixed
    {
        if (!$this->foreignKeyCaptured) {
            throw new LogicException('No foreign key has been captured for this relation.');
        }

        return $this->foreignKey;
    }
}
