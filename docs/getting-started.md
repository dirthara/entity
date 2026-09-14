---
id: getting-started
title: Getting started
sidebar_position: 3
description: Map a class, read a row back as that class, and write one.
---

# Getting started

Every example assumes a wired `$entities`, as built in
[Installation](installation.md), and a table that already exists. This package
maps onto tables; creating them is `dirthara/schema`'s job.

## Mapping a class

An entity is a plain class with typed properties. One property must be the
identifier.

```php
use Dirthara\Entity\Attribute\Entity;
use Dirthara\Entity\Attribute\Generated;
use Dirthara\Entity\Attribute\Id;

#[Entity]
final class Article
{
    #[Id]
    #[Generated]
    public int $id;

    public string $title;

    public bool $published;
}
```

That maps to a table named `articles` with columns `id`, `title` and
`published`. Nothing declares the table or the column names, because
[the naming strategy](mapping/naming.md) derives them: the class name is
snake-cased and pluralised, and each property name is snake-cased.

`#[Generated]` says the database supplies the value, so it is left out of an
insert and read back afterwards.

## Getting a store

`of()` takes the class and returns a store for it.

```php
$articles = $entities->of(Article::class);
```

The store is typed: `$articles->find(1)` is an `Article|null`, not an `object`.
Metadata is read the first time a class is asked for and cached after that, so
calling `of()` again is cheap.

## Writing a row

```php
$article = new Article();
$article->title = 'Hello';
$article->published = false;

$articles->insert($article);

$article->id;   // the generated identifier, read back from the database
```

`insert()` returns nothing: it either wrote the row or threw. Because `id` is
`#[Generated]` and the whole identifier, the value the database assigned is
written back onto the object.

## Reading it back

```php
$articles->find(1);          // Article|null
$articles->findOrFail(1);    // Article, or EntityNotFoundException
$articles->all();            // Collection<int, Article>
$articles->count();          // int
$articles->exists();         // bool
```

`all()` answers with a `Dirthara\Collection\Contract\Collection` keyed by
position. Use `values()` for a plain list, or iterate it directly.

```php
foreach ($articles->all() as $article) {
    echo $article->title;
}
```

## Changing and deleting

```php
$article = $articles->findOrFail(1);
$article->published = true;

$articles->update($article);   // int: rows affected
$articles->delete($article);   // int: rows the identifier matched
```

Both find the row by its identifier, taken from the entity you hand them.

:::caution
`update()` writes every mapped column, not the ones you changed, because
nothing tracks that. Its return value is the count the driver reports, and the
drivers disagree about what to count — see
[Entity manager and stores](entities.md#what-a-write-returns).
:::

## Querying

`query()` returns a builder scoped to the entity's table, and speaks in property
names rather than column names.

```php
use Dirthara\Database\Query\Sql\ComparisonOperator;

$published = $articles->query()
    ->where('published', ComparisonOperator::Equal, true)
    ->orderByDesc('title')
    ->limit(10)
    ->get();
```

See [Querying](querying.md) for every condition and for `first()`, `cursor()`
and the aggregates.

## Where to go next

- [Mapping attributes](mapping/attributes.md) to rename a column, ignore a
  property or name a converter.
- [Identifiers](mapping/identifiers.md) for composite and assigned keys.
- [Converters](types/converters.md) to map an enum, an array or a type no
  converter handles yet.
