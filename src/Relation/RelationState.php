<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation;

use Dirthara\Entity\Exception\RelationLoadingException;

final class RelationState
{
    private bool $loaded = false;

    private bool $foreignKeyCaptured = false;

    private mixed $foreignKey = null;

    /**
     * @param class-string $entity
     */
    public function __construct(
        private readonly string $entity,
        private readonly string $relation,
    ) {}

    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    public function markLoaded(): void
    {
        $this->loaded = true;
    }

    public function markUnloaded(): void
    {
        $this->loaded = false;
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

    /**
     * @throws RelationLoadingException
     */
    public function foreignKey(): mixed
    {
        if (!$this->foreignKeyCaptured) {
            throw RelationLoadingException::foreignKeyNotCaptured(entity: $this->entity, relation: $this->relation);
        }

        return $this->foreignKey;
    }
}
