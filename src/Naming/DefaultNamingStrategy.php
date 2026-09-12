<?php

declare(strict_types=1);

namespace Dirthara\Entity\Naming;

final readonly class DefaultNamingStrategy implements NamingStrategy
{
    private const array IRREGULAR_PLURALS = [
        'person' => 'people',
        'child' => 'children',
    ];

    public function table(string $entityShortName): string
    {
        return $this->pluralize($this->snakeCase($entityShortName));
    }

    public function column(string $property): string
    {
        return $this->snakeCase($property);
    }

    private function snakeCase(string $value): string
    {
        $value = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $value);

        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $value ?? '');

        return strtolower($value ?? '');
    }

    private function pluralize(string $value): string
    {
        if (isset(self::IRREGULAR_PLURALS[$value])) {
            return self::IRREGULAR_PLURALS[$value];
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
}
