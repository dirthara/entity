<?php

declare(strict_types=1);

namespace Dirthara\Entity\Naming;

final readonly class DefaultNamingStrategy implements NamingStrategy
{
    private const array IRREGULAR_PLURALS = [
        'person' => 'people',
        'child' => 'children',
        'man' => 'men',
        'woman' => 'women',
        'mouse' => 'mice',
        'goose' => 'geese',
        'tooth' => 'teeth',
        'foot' => 'feet',
        'ox' => 'oxen',
        'analysis' => 'analyses',
        'basis' => 'bases',
        'crisis' => 'crises',
        'thesis' => 'theses',
        'deer' => 'deer',
        'sheep' => 'sheep',
        'species' => 'species',
        'series' => 'series',
    ];

    public function table(string $entityShortName): string
    {
        return $this->pluralize($this->snakeCase($entityShortName));
    }

    public function column(string $property): string
    {
        return $this->snakeCase($property);
    }

    public function relationForeignKey(string $property, string $identifierColumn): string
    {
        return sprintf('%s_%s', $this->column($property), $this->column($identifierColumn));
    }

    public function entityForeignKey(string $entityShortName, string $identifierColumn): string
    {
        $entity = $this->snakeCase($entityShortName);
        $identifier = $this->column($identifierColumn);

        if ($identifier === $entity || str_starts_with($identifier, $entity . '_')) {
            return $identifier;
        }

        return sprintf('%s_%s', $entity, $identifier);
    }

    public function joinTable(string $entityShortName, string $relatedEntityShortName): string
    {
        $entities = [
            $this->snakeCase($entityShortName),
            $this->snakeCase($relatedEntityShortName),
        ];

        sort($entities);

        return implode('_', $entities);
    }

    private function snakeCase(string $value): string
    {
        $value = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $value);

        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $value ?? '');

        return strtolower($value ?? '');
    }

    private function pluralize(string $value): string
    {
        $irregular = $this->irregularPlural($value);

        if ($irregular !== null) {
            return $irregular;
        }

        if (preg_match('/[^aeiou]y$/', $value) === 1) {
            return substr($value, 0, -1) . 'ies';
        }

        if (
            str_ends_with($value, 's')
            || str_ends_with($value, 'x')
            || str_ends_with($value, 'z')
            || str_ends_with($value, 'ch')
            || str_ends_with($value, 'sh')
        ) {
            return $value . 'es';
        }

        return $value . 's';
    }

    private function irregularPlural(string $value): ?string
    {
        foreach (self::IRREGULAR_PLURALS as $singular => $plural) {
            if ($value === $singular || str_ends_with($value, '_' . $singular)) {
                return substr($value, 0, -strlen($singular)) . $plural;
            }
        }

        return null;
    }
}
