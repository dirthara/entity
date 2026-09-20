<?php

declare(strict_types=1);

namespace Dirthara\Entity\Relation;

use Dirthara\Entity\Exception\MappingException;

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
     *
     * @throws MappingException
     */
    public static function fromPaths(array $paths): self
    {
        /** @var array<string, list<string>> $branches */
        $branches = [];

        foreach ($paths as $path) {
            $segments = explode('.', $path);

            foreach ($segments as $segment) {
                if ($segment === '') {
                    throw MappingException::invalidRelationPath($path);
                }
            }

            $relation = $segments[0];
            $rest = array_slice($segments, 1);

            $branches[$relation] ??= [];

            if ($rest !== []) {
                $branches[$relation][] = implode('.', $rest);
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

    public function has(string $relation): bool
    {
        return isset($this->nested[$relation]);
    }

    public function nestedFor(string $relation): self
    {
        return $this->nested[$relation] ?? new self([]);
    }
}
