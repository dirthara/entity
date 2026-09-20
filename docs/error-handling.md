---
id: error-handling
title: Error handling
sidebar_position: 9
description: The exception hierarchy, the context each exception carries, and what to log.
---

# Error handling

## The hierarchy

Every exception the package throws extends `EntityException`, so one `catch`
covers all of them.

```text
EntityException
├── MappingException
├── TypeConversionException
├── CreateEntityException
├── HydrationException
├── PersistenceException
├── InvalidEntityException
├── InvalidIdentifierException
├── EntityNotFoundException
├── RelationLoadingException
└── EntityDatabaseException
```

| Exception | Thrown when |
| --- | --- |
| `MappingException` | A class cannot be mapped: no identifier, contradictory attributes, an untyped or unsupported property, two properties on one column, a converter that cannot be built. |
| `TypeConversionException` | No converter handles a type, or a value cannot cross the boundary in either direction. |
| `CreateEntityException` | An entity could not be instantiated to hydrate into. |
| `HydrationException` | A row cannot fill an entity: a column missing, a `NULL` in a property that refuses it, a value the property's type rejects. |
| `PersistenceException` | A write cannot be made or was refused: an uninitialised property, no generated identifier returned, an identifier matching more than one row, or a relation that cannot be written. |
| `InvalidEntityException` | A store was handed an object of another class. |
| `InvalidIdentifierException` | The identifier passed to `find()` does not fit the entity's. |
| `EntityNotFoundException` | `findOrFail()` found nothing. |
| `RelationLoadingException` | A relation cannot be loaded: a foreign key pointing at nothing, a `HasOne` matching several rows, a missing key column, or a cursor asked for relations. |
| `EntityDatabaseException` | A read or a connection failed in the database layer. |

`MappingException` and `TypeConversionException` are raised while reading a
class, so a class that cannot be mapped fails on the first `of()` call rather
than when a row is read.

All ten are `final`. `EntityException` is the one open class, and it is what to
catch to catch everything.

## Context

`EntityException` carries an `array<string, mixed>` of diagnostic data
alongside the message.

```php
try {
    $articles->insert($article);
} catch (EntityException $exception) {
    $logger->error($exception->getMessage(), $exception->getContext() + [
        'exception' => $exception,
    ]);
}
```

The `exception` key must contain the caught exception even when the context
already has an entry under that name.

`addContext()` merges more in and returns the exception, so a layer that knows
something the thrower did not can add it and rethrow:

```php
throw $exception->addContext(['request' => $requestId]);
```

### What each carries

| Source | Keys |
| --- | --- |
| Anything about one entity | `entity` |
| A property-level failure | `entity`, `property` |
| A column-level failure | `entity`, `property`, `column` |
| A duplicate column | `entity`, `column`, `firstProperty`, `secondProperty` |
| Contradictory attributes | `entity`, `property`, `attributes` |
| An unsupported property type | `entity`, `property`, `type` |
| A converter that cannot be built | `entity`, `property`, `converter`, `reason` |
| A closure that did not answer a converter | `entity`, `property`, `returned` |
| A failed read or connection | `entity`, `operation` |
| Too many rows affected | `entity`, `operation`, `expectedMaximum`, `actual` |
| The wrong class | `expected`, `actual` |
| A conversion | `expected` and `actual`, or `type` and `value` |
| Nothing found | `type`, `identifier` |
| A composite identifier | `entity`, `property`, or `properties` |
| A relation-level failure | `entity`, `relation` |
| A relation and what it points at | `entity`, `relation`, `target` |
| A related row that is missing or already taken | `entity`, `relation`, `target`, plus `identifier` or `owner` |
| Too many related rows | `entity`, `relation`, `expectedMaximum`, `actual` |
| A cursor refusing relations | `entity`, `relations` |

`operation` is `insert`, `update`, `delete`, `get`, `first`, `cursor`, `exists`,
`count` or `connect`.

:::danger
Context is written to logs, and a conversion failure records the value it could
not convert. Do not map a secret to a column whose converter can fail, and treat
`value` as sensitive when configuring a logger.
:::

## The original is always attached

An exception raised by `dirthara/database` is wrapped, not swallowed. The
original is the `previous` exception, so the driver's own message and SQLSTATE
stay reachable.

```php
catch (EntityDatabaseException $exception) {
    $exception->getPrevious();   // the DatabaseException from dirthara/database
}
```

Wrapping is deliberate: a caller should not need `dirthara/database` in a
`catch` block to handle a failure this package caused. Reflection failures are
wrapped the same way, with the `ReflectionException` as `previous`.

| Operation | Wrapped as |
| --- | --- |
| `insert()`, `update()`, `delete()` | `PersistenceException` |
| `get()`, `first()`, `cursor()`, `exists()`, `count()` | `EntityDatabaseException` |
| Loading a relation | `EntityDatabaseException`, `operation` of `load relation "name"` |
| Writing a relation through a handle | `PersistenceException` |
| Resolving a connection in `of()` | `EntityDatabaseException`, `operation` of `connect` |

## Catching the right thing

The useful split is between a mapping that is wrong and a row that is:

```php
use Dirthara\Entity\Exception\MappingException;
use Dirthara\Entity\Exception\HydrationException;

try {
    $articles->all();
} catch (MappingException $exception) {
    // the class is mapped wrongly; fix the code
} catch (HydrationException $exception) {
    // the class is fine and this row does not fit it; the schema drifted
}
```

`HydrationException` is the one that tells you the database and the mapping
disagree — a column renamed underneath you, or a column made nullable while the
property still refuses `null`.

`EntityNotFoundException` is the odd one out: it is an expected outcome, not a
fault. Use `find()` and check for `null` when absence is ordinary, and
`findOrFail()` only where absence really is exceptional.
