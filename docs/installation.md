---
id: installation
title: Installation
sidebar_position: 2
description: Requirements, the Composer package, and wiring an EntityManager.
---

# Installation

```sh
composer require dirthara/entity
```

## Requirements

| Requirement | Why |
| --- | --- |
| PHP 8.5 | Reflection writes raw property values, and the code uses `new` without parentheses and typed class constants. |
| `dirthara/database` `^0.1` | Owns the connections, drivers and query builder this package maps onto. |
| `dirthara/collection` `^0.1` | A result set is returned as a collection rather than an array. |

Composer installs both Dirthara packages for you. This package never touches PDO
itself, but `dirthara/database` does, so each database needs its own PDO
extension. Install only the ones you use:

| Database | Extension |
| --- | --- |
| MySQL | `pdo_mysql` |
| PostgreSQL | `pdo_pgsql` |
| SQLite | `pdo_sqlite` |
| SQL Server | `pdo_sqlsrv` |

## Wiring it up

An `EntityManager` needs four collaborators: the `Database` to run queries on, a
registry that reads mapping from your classes, a hydrator that fills an entity
from a row, and a persister that writes one back.

```php
use Dirthara\Entity\EntityManager;
use Dirthara\Entity\Metadata\MetadataFactory;
use Dirthara\Entity\Metadata\MetadataRegistry;
use Dirthara\Entity\Naming\DefaultNamingStrategy;
use Dirthara\Entity\Hydration\ReflectionHydrator;
use Dirthara\Entity\Persistence\ReflectionPersister;
use Dirthara\Entity\Type\TypeRegistry;

$types = new TypeRegistry();

$entities = new EntityManager(
    database: $database,
    metadata: new MetadataRegistry(new MetadataFactory(new DefaultNamingStrategy(), $types)),
    hydrator: new ReflectionHydrator(),
    persister: new ReflectionPersister(),
);
```

`$database` is the `Dirthara\Database\Database` from `dirthara/database`. See
that package's installation page for building one.

| Collaborator | Interface | What to replace it for |
| --- | --- | --- |
| `MetadataRegistry` | none, it is a final class | Nothing; it caches `MetadataFactory` per class. |
| `DefaultNamingStrategy` | `NamingStrategy` | A different table or column convention. |
| `TypeRegistry` | none, it is a final class | Registering your own converters, or replacing a built-in. |
| `ReflectionHydrator` | `Hydrator` | Filling entities some other way than reflection. |
| `ReflectionPersister` | `EntityPersister` | Writing entities some other way than reflection. |

:::tip
A framework integration would build this once and hand the `EntityManager` to
application code. Nothing here opens a connection, and metadata is read the
first time a class is asked for, so building it early costs nothing.
:::

A `TypeRegistry` with nothing passed to it already handles every scalar, an
`array`, a date and time at four precisions, and any backed enum you map.
Registering is for your own types and for replacing a built-in; see
[Converters](types/converters.md).

Next: [Getting started](getting-started.md).
