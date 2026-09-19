---
id: intro
title: Dirthara Entity
sidebar_label: Introduction
sidebar_position: 1
description: Map a PHP class to a table with attributes, then read and write it through a typed store.
---

# Dirthara Entity

Describe a class with attributes and read or write its table through a store
that hands you back the class, not an array.

```php
#[Entity]
final class Article
{
    #[Id]
    #[Generated]
    public int $id;

    public string $title;

    public bool $published;
}

$articles = $entities->of(Article::class);

$article = $articles->findOrFail(1);
$article->published = true;
$articles->update($article);
```

## What it does

- **Mapping is declared, not configured.** A class becomes a table and its
  typed properties become columns. The attributes are there for what a
  convention cannot guess.
- **Values cross the boundary through a converter.** Every property has one, so
  a `bool` is written the way the database wants it and read back as a `bool`
  whatever the driver reports. Scalars and backed enums need no setting up.
- **The store is typed.** `of(Article::class)` returns a store whose `find()`,
  `all()` and `first()` are `Article`, so a static analyzer follows the entity
  through your code.
- **Failures name the entity and the property.** Every exception carries the
  class, the property and the operation as context, ready for a log.

## What it does not do

- There is no identity map and no unit of work. `insert()`, `update()` and
  `delete()` each go straight to the database, and reading the same row twice
  gives you two objects.
- There are no relations. A foreign key is an ordinary column; loading what it
  points at is a second query you write.
- There is no change tracking. `update()` writes every mapped column, because
  nothing recorded which ones you touched.
- There are no partial reads. A row must carry every mapped column, so there is
  no `select()` and no lazy column.
- It does not create tables. Use `dirthara/schema` for that.

## Where to go next

- [Installation](installation.md) for the requirements and wiring an
  `EntityManager`.
- [Getting started](getting-started.md) to map a class and write a row.
- [Mapping attributes](mapping/attributes.md) for every attribute and option.
- [Converters](types/converters.md) to pick the precision a date column holds,
  or to map a type the built-in converters do not cover.
- [Error handling](error-handling.md) for what each failure means.
