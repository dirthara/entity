---
id: entities
title: Entity manager and stores
sidebar_position: 4
description: EntityManager, EntityStore, choosing a connection, and what a write returns.
---

# Entity manager and stores

## Two classes

`EntityManager` is the entry point. It holds the collaborators and hands out a
store per entity class.

`EntityStore` is that store: one entity class, one connection, and the reads and
writes for it.

```php
$articles = $entities->of(Article::class);
```

A store is cheap to make and holds no state of its own, so there is no need to
keep one around. The metadata behind it is cached by class in the
`MetadataRegistry`, not by the store.

## EntityManager

| Method | Returns |
| --- | --- |
| `of(string $entity, ?string $connection = null)` | `EntityStore<T>` |

`$entity` is a class name. Mapping is read on the first call for that class and
cached; a class that cannot be mapped throws
[`MappingException`](error-handling.md), and one whose property type has no
converter throws `TypeConversionException`.

## EntityStore

| Method | Returns | Notes |
| --- | --- | --- |
| `query()` | `EntityQuery<T>` | A fresh query each call. |
| `find(mixed $identifier)` | `T\|null` | |
| `findOrFail(mixed $identifier)` | `T` | Throws `EntityNotFoundException` when missing. |
| `all()` | `Collection<int, T>` | Every row in the table. |
| `count()` | `int` | |
| `exists()` | `bool` | |
| `insert(object $entity)` | `void` | Writes back a generated identifier. |
| `update(object $entity)` | `int` | Rows the driver reports as affected. |
| `delete(object $entity)` | `int` | Rows the identifier matched. |
| `load(object $entity, array $relations = [], array $without = [])` | `void` | Fills relations on an entity you already have. |
| `relation(object $entity, string $relation)` | `RelationHandle` | A handle for writing one relation. |
| `belongsToOne()`, `hasOne()`, `hasMany()`, `belongsToMany()` | a typed handle | The same, narrowed to one relation kind. |

`all()`, `count()` and `exists()` are the unfiltered forms of the same methods
on [a query](querying.md); reach for `query()` as soon as you need a condition.

The last four are described in [loading](relations/loading.md) and
[writing relations](relations/writing.md).

Handing a store an object of another class throws `InvalidEntityException`. The
store checks before it does anything, so a mistyped call cannot write a row.

## Choosing a connection

The connection comes from one of three places, in this order:

1. The name passed to `of()`.
2. The `connection` option on the entity's `#[Entity]` attribute.
3. The default connection configured in `dirthara/database`.

```php
#[Entity(connection: 'replica')]
final class Replica { /* ... */ }

$entities->of(Replica::class);              // the 'replica' connection
$entities->of(Replica::class, 'primary');   // overridden to 'primary'
$entities->of(Article::class);              // the default connection
```

A connection that cannot be resolved or reached throws
`EntityDatabaseException` with `operation` set to `connect`.

## What a write returns

`insert()` returns nothing. It wrote the row or it threw.

`delete()` returns the number of rows the identifier matched, which every
supported driver counts the same way. For an entity with a complete identifier
that is `1` when the row was there and `0` when it was not.

`update()` returns the number of rows the driver reports as affected, and this
is the one place where the answer depends on the database:

| Driver | An update that changes a value | An update that writes the values the row already holds |
| --- | --- | --- |
| MySQL | `1` | `0` |
| PostgreSQL | `1` | `1` |
| SQLite | `1` | `1` |
| SQL Server | `1` | `1` |

MySQL counts the rows whose values changed; the others count the rows the
identifier matched. The package cannot smooth this over, because it does not own
the connection: setting `PDO::MYSQL_ATTR_FOUND_ROWS` is a decision for whoever
configures the `ConnectionConfig`, and changing it there would change the count
for everything else on that connection too.

:::caution
`update()` returning `0` does not mean the row is missing. On MySQL it also
means the row was already identical. If you need to know whether the row exists,
ask with `exists()` or `find()` rather than reading it out of the count.
:::

An identifier that matches more than one row is treated as a fault, not as a
bulk write: `update()` and `delete()` throw `PersistenceException` with
`expectedMaximum` and `actual` in the context rather than reporting the larger
number.

An `update()` on an entity whose only mapped columns are its identifier has
nothing to write, so it returns `0` without sending a statement.

## What a write reads back

Only one thing is written back onto your object: the identifier a database
generated, and only when the entity has a single-property identifier marked
`#[Generated]`. That is all the database reports from an insert.

:::caution
`#[Generated]` on any other property is inert. The column is left out of inserts
and updates, and nothing is read back, so the property stays uninitialised until
you load the entity again. A `created_at` filled by a database default works
this way: insert the entity, then `find()` it to see the value.
:::

Everything else on the object is exactly what you set. There is no change
tracking and no identity map, so two reads of the same row are two objects, and
nothing notices if one of them is stale.
