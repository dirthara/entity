---
id: loading
title: Loading relations
sidebar_position: 2
description: with(), eager loading, load(), how batching works, and where a cursor stops.
---

# Loading relations

Nothing is loaded unless you ask. A relation property stays uninitialised until
something fills it, which is deliberate: reading a row should cost one query.

## Asking for one on a query

`with()` names relations to load alongside the rows.

```php
$books = $books->query()->with('writer', 'chapters')->get();

foreach ($books as $book) {
    $book->writer->name;
    $book->chapters;
}
```

A name the entity does not map throws `MappingException` from `with()` itself,
so a typo fails where you wrote it. Every segment of a path is checked, not just
the first.

## Asking for one afterwards

`EntityStore::load()` fills relations on an entity you already have.

```php
$book = $books->findOrFail(1);

$books->load($book, ['writer', 'topics']);
```

A relation already loaded is left alone, so calling `load()` twice costs one
round of queries, not two.

## Loading without asking

`RelationLoading::Eager` on the attribute loads a relation whenever its entity
is loaded — as the thing you queried for, and as the target of another relation.

```php
#[BelongsToOne(loading: RelationLoading::Eager)]
public Book $book;
```

So a query for reviews loads each review's book, and if `Book` marks its writer
`Eager`, those writers come too. Each level is still one query.

Use it for a relation the entity is not really usable without. Everything else
is better named at the call site, where the cost is visible.

:::caution
An eager relation is not followed back to an entity already on the path. If
`Book` eagerly loads its chapters and `Chapter` eagerly loads its book, loading
a book gives you its chapters and stops there — the chapters' `book` is left
unloaded rather than fetching the book again forever. A path you write yourself
is always followed, so `with('chapters.book')` still works.
:::

## Leaving one out

`without()` drops a relation that would otherwise load, which is how you opt out
of an eager one:

```php
$reviews->query()->without('book')->get();
```

It takes paths too, so you can keep a relation and drop something beneath it:

```php
$reviews->query()->without('book.writer')->get();   // the book, but not its writer
```

`load()` takes the same list as a third argument:

```php
$reviews->load($review, ['book'], without: ['book.writer']);
```

Naming a relation in both `with()` and `without()` leaves it out; `without()`
is the more specific instruction, so it wins. A name that is not a relation
throws `MappingException`, the same as `with()`.

Dropping the last relation an entity would load makes a cursor legal again:

```php
$reviews->query()->without('book')->cursor();   // fine
```

## One query per relation, not per row

`get()` reads its whole page first, then loads each relation for every entity in
one go — one query per relation, whatever the page size. A `BelongsToMany` takes
two: one for the join table and one for the targets.

Owners sharing a foreign key get the same object back:

```php
$books = $books->query()->with('writer')->get();

$books->get(0)->writer === $books->get(2)->writer;   // true, same row
```

That is a consequence of batching rather than an identity map. Two separate
queries still give you two objects; see
[what this package does not do](../intro.md#what-it-does-not-do).

## Relations of relations

A dot names a relation of the relation before it, to any depth:

```php
$books = $books->query()->with('writer.books.chapters')->get();

$books->get(0)->writer->books->get(0)->chapters;
```

`load()` takes the same paths:

```php
$books->load($book, ['writer.books']);
```

Each level is still one query for the whole batch, not one per parent. The page
above costs four: the books, their writers, those writers' books, and those
books' chapters — whatever the page size.

Paths sharing a head are loaded once. `with('writer.books', 'writer')` reads the
writers a single time and then their books.

A relation you already loaded is not read again, but a path still continues
through it:

```php
$books->load($book, ['writer']);
$books->load($book, ['writer.books']);   // the writer stays as it is; its books load
```

## Cursors cannot load relations

`cursor()` holds one row at a time, so it has nothing to batch. Rather than
issue a query per row, it refuses:

```php
$books->query()->with('writer')->cursor();   // RelationLoadingException
```

The same applies without `with()` when the entity declares a relation as
`Eager`, because that relation would be loaded anyway. The exception is thrown
by the `cursor()` call itself, not on the first iteration.

A cursor still records the foreign keys it reads, so an entity you streamed can
load a relation afterwards, one entity at a time:

```php
foreach ($books->query()->cursor() as $book) {
    $books->load($book, ['writer']);   // fine, and one query per book
}
```

When you want the relations and a bounded amount of memory, page with `get()`
instead — see [streaming with `cursor()`](../querying.md#streaming-with-cursor).

## What ends up in the property

| Relation | Property type | Value |
| --- | --- | --- |
| To one | `Writer` | The entity. |
| To one | `?Writer` | The entity, or `null` when the key is `NULL`. |
| To many | `Collection` or `iterable` | An `ImmutableCollection`, empty when nothing matches. |
| To many | `array` | A plain array, empty when nothing matches. |

## What is refused, and why

| Situation | Result |
| --- | --- |
| A `BelongsToOne` key is `NULL` on a property that is not nullable | `RelationLoadingException`: null not allowed. |
| A `BelongsToOne` key points at a row that is not there | `RelationLoadingException`: related entity not found. |
| A `HasOne` finds no row, on a property that is not nullable | `RelationLoadingException`: related entity not found. |
| A `HasOne` finds more than one row | `RelationLoadingException`: multiple related entities. |
| A join table row points at a target that is not there | `RelationLoadingException`: related entity not found. |
| The entity's identifier is not set | `RelationLoadingException`: identifier not initialised. |
| A foreign key column is missing from the row | `RelationLoadingException`: missing foreign key column. |

A missing row is an error rather than a silent `null` because a foreign key
pointing nowhere means the database and the mapping disagree, which is the same
reasoning behind `HydrationException`.

:::caution
A `BelongsToOne` needs its foreign key column present in every row that is
hydrated, whether or not you load the relation — that is when the key is
recorded. Queries select every column, so this only bites if the column is
missing from the table itself.
:::

## Not yet supported

- **Conditions on a relation.** A relation loads whole; filter what you got.
