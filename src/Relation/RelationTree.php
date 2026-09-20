<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation;

final readonly class RelationTree
{
    /**
     * @param array<string, self> $nested
     */
    private function __construct(
        private array $nested,
    ) {}

    /**
     * @param list<string> $paths
     */
    public static function fromPaths(array $paths): self
    {
        /** @var array<string, list<string>> $branches */
        $branches = [];

        foreach ($paths as $path) {
            [$relation, $rest] = array_pad(explode('.', $path, limit: 2), 2, null);

            if ($relation === '' || $relation === null) {
                continue;
            }

            $branches[$relation] ??= [];

            if ($rest !== null && $rest !== '') {
                $branches[$relation][] = $rest;
            }
        }

        return new self(array_map(self::fromPaths(...), $branches));
    }

    public function isEmpty(): bool
    {
        return $this->nested === [];
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return array_keys($this->nested);
    }

    public function nestedFor(string $relation): self
    {
        return $this->nested[$relation] ?? new self([]);
    }
}
