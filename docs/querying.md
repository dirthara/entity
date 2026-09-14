---
id: querying
title: Querying
sidebar_position: 6
description: Conditions, ordering, paging, and the four ways to run an entity query.
---

# Querying

`query()` returns an `EntityQuery` scoped to the entity's table. It speaks in
property names: it translates each one to its column and puts the value through
that property's converter, so you compare against PHP values rather than
database ones.

```php
use Dirthara\Database\Query\Sql\ComparisonOperator;

$articles->query()
    ->where('published', ComparisonOperator::Equal, true)
    ->orderByDesc('title')
    ->limit(10)
    ->get();
```

A property the entity does not map throws `MappingException`, so a typo is a
failure rather than a silent mismatch.

## Conditions

| Method | Notes |
| --- | --- |
| `where(string $property, ComparisonOperator\|string $operator, mixed $value)` | The operator may be an enum case or its string, such as `'>'`. |
| `orWhere(string $property, ComparisonOperator\|string $operator, mixed $value)` | |
| `whereNull(string $property)` | |
| `whereNotNull(string $property)` | |
| `whereIn(string $property, iterable $values)` | |
| `whereNotIn(string $property, iterable $values)` | |
| `whereBetween(string $property, mixed $from, mixed $to)` | |
| `whereNested(Closure $callback)` | The callback receives an `EntityQuery` for the same entity. |

`where()` accepts `null` and puts it through as `NULL`, so
`where('bio', ComparisonOperator::Equal, null)` compiles to a comparison against
`NULL`. Reach for `whereNull()` when you mean `IS NULL`.

```php
$articles->query()
    ->where('published', ComparisonOperator::Equal, true)
    ->whereNested(function (EntityQuery $query): void {
        $query->where('title', ComparisonOperator::Equal, 'First')
            ->orWhere('title', ComparisonOperator::Equal, 'Second');
    })
    ->get();
```

:::caution
`whereIn()` and `whereNotIn()` do not accept `null` among their values. `IN`
cannot match `NULL`, so every value goes through the converter and a `null` is
refused with `TypeConversionException`. Combine `whereNull()` with `orWhere` if
you need both.
:::

## Ordering and paging

| Method | Notes |
| --- | --- |
| `orderBy(string $property, OrderDirection $direction = OrderDirection::Ascending)` | |
| `orderByDesc(string $property)` | |
| `limit(int $limit)` | |
| `offset(int $offset)` | |

## Running it

| Method | Returns | Notes |
| --- | --- | --- |
| `get()` | `Collection<int, T>` | Every matching row, hydrated. |
| `first()` | `T\|null` | Applies `LIMIT 1` for you. |
| `cursor()` | `iterable<T>` | A generator, one entity at a time. |
| `exists()` | `bool` | |
| `count()` | `int` | |

```php
$articles->query()->where('id', '>', 10)->first();
```

### Streaming with `cursor()`

`cursor()` is a generator, so nothing runs until you iterate it. That also means
a database failure surfaces on the first iteration rather than on the call.

```php
foreach ($articles->query()->cursor() as $article) {
    // one Article at a time, not the whole table in memory
}
```

## A query is a builder, not a value

Each condition mutates the query and returns the same object, so a query is not
safe to share or to reuse for a second, different query.

```php
$query = $articles->query();
$query->where('published', ComparisonOperator::Equal, true);
$query->where('title', ComparisonOperator::Equal, 'Hello');
// one query with both conditions
```

`query()` hands back a fresh one every call, so start there rather than reusing
a query you have already run.

## Every column is read

There is no `select()`. Hydration needs every mapped column present in the row,
and a missing one throws `HydrationException`. That is the trade for entities
that are always complete: an entity is never half-loaded, and there are no lazy
columns to trip over later.

If you want a projection rather than entities, use the query builder from
`dirthara/database` directly on the same connection.
