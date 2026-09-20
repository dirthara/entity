---
id: writing
title: Writing relations
sidebar_position: 3
description: Foreign keys on insert and update, and the handle API for linking and unlinking rows.
---

# Writing relations

A `BelongsToOne` is a column of the entity's own table, so it is written with
the rest of the row. Everything else lives on another table and is written
through a handle.

## A belongs-to-one on insert and update

Assign the related entity and save; its identifier becomes the foreign key.

```php
$book = new Book();
$book->title = 'Earthsea';
$book->writer = $ursula;
$book->editor = null;

$books->insert($book);   // INSERT INTO books (title, writer_id, editor_id) ...
```

Insert and update treat an unset property differently, because they are asking
different questions:

| | Property never set | Property set to `null` | Property set to an entity |
| --- | --- | --- | --- |
| `insert()` | `PersistenceException` | `NULL`, if the property is nullable | The identifier |
| `update()` | Left as the row has it | `NULL`, if the property is nullable | The identifier |

Insert refuses an unset relation for the same reason it refuses an unset
scalar: uninitialised is not `null`, and a new row has no previous value to fall
back on. Say `null` if you mean `null`.

Update skips it because reading a row does not load its relations. Without that,
an ordinary edit would erase every foreign key you never looked at:

```php
$book = $books->findOrFail(1);   // writer not loaded
$book->title = 'Changed';

$books->update($book);           // writer_id is left alone
```

A related entity with no identifier yet throws `PersistenceException`: insert it
first.

## Handles

Everything else is written through a handle for one entity and one relation.
Each relation kind has its own, with only the verbs that make sense for it.

```php
$books->belongsToOne($book, 'writer')->associate($ursula);
$books->hasOne($book, 'jacket')->associate($jacket);
$books->hasMany($book, 'chapters')->add($chapter);
$books->belongsToMany($book, 'topics')->attach($topic);
```

| Method | Returns |
| --- | --- |
| `belongsToOne(object $entity, string $relation)` | `BelongsToOneHandle` |
| `hasOne(object $entity, string $relation)` | `HasOneHandle` |
| `hasMany(object $entity, string $relation)` | `HasManyHandle` |
| `belongsToMany(object $entity, string $relation)` | `BelongsToManyHandle` |
| `relation(object $entity, string $relation)` | `RelationHandle` |

Asking for the wrong kind throws `PersistenceException` naming both. Use
`relation()` when you want whatever the relation is and will narrow it
yourself; it is the introspection form, and `relation()->relation()` answers the
metadata.

Every verb writes immediately, so the entity needs an identifier already. None
of them return a count.

## To-one relations

```php
$books->belongsToOne($book, 'writer')->associate($ursula);
$books->belongsToOne($book, 'editor')->dissociate();
```

`associate()` sets the property and writes the key in one go. `dissociate()`
clears both, and needs the property to be nullable — a relation the entity
declares as required cannot be dropped, and that throws rather than writing a
`NULL` the type refuses.

`hasOne()` takes the same two verbs, but the key is on the other table, so
`associate()` means **replace**:

```php
$books->hasOne($book, 'jacket')->associate($jacket);
```

It releases whatever currently points at this book, then claims the new row,
both in one transaction. Releasing first is what lets it work under a unique
index on the foreign key.

| Target state | Result |
| --- | --- |
| Not pointed at by anyone | Claimed. |
| Already this owner's | Nothing changes. |
| Pointed at by another owner | `PersistenceException`: the target already belongs to someone else. |
| The row is not there | `PersistenceException`: the target does not exist. |

Taking a row from another owner is refused rather than done quietly, because it
edits a second entity's relation as a side effect — and if that entity is also
in memory, nothing can tell it. Reassigning is a two-step job: dissociate the
first owner, then associate the second.

:::caution
If releasing would touch more than one row, `associate()` and `dissociate()`
throw instead. A `HasOne` matching several rows means the stored data already
breaks the relation, and the package says so rather than tidying it away. The
transaction rolls the attempted cleanup back.
:::

## To-many relations

```php
$books->hasMany($book, 'chapters')->add($chapter);
$books->hasMany($book, 'chapters')->remove($chapter);
```

`add()` writes this book's identifier onto the chapter. `remove()` clears it,
and only for a chapter this book actually owns, so removing someone else's is a
no-op. Both need the target's foreign key column to accept `NULL`; if it does
not, the database refuses and you get a `PersistenceException` wrapping it.

```php
$topics = $books->belongsToMany($book, 'topics');

$topics->attach($topic);
$topics->detach($topic);
$topics->sync([$fantasy, $scifi]);
```

`attach()` is idempotent: attaching something already attached does nothing
rather than adding a second join-table row. `sync()` makes the relation exactly
what you pass, deleting and inserting the difference in one transaction — either
the relation ends up as the list you gave, or nothing changes.

### What happens to the property

A to-many verb cannot splice a row into a collection you are holding, so it
reloads instead — but only if you had loaded it:

```php
$books->load($book, ['chapters']);
$books->hasMany($book, 'chapters')->add($chapter);

$book->chapters;   // includes the new chapter
```

```php
$book = $books->findOrFail(1);          // chapters not loaded
$books->hasMany($book, 'chapters')->add($chapter);

$book->chapters;                        // still uninitialised
```

Nothing is loaded just because it changed, and nothing you were holding is left
stale.

:::caution
Reloading goes one level deep. If you had loaded `topics.books` and then attach
a topic, the topics are read again but their `books` are not — the entities on
that second level are new objects with nothing beneath them. Eager relations of
the reloaded targets still load, because those load wherever their entity does.
Name the path again if you need the deeper level back.
:::

## What is refused, and why

| Situation | Result |
| --- | --- |
| The entity has no identifier | `PersistenceException`: identifier not initialised. |
| The related entity has no identifier | `PersistenceException`: it has not been saved yet. |
| The related entity is not the relation's target | `PersistenceException` naming both classes. |
| `dissociate()` on a relation that is not nullable | `PersistenceException`: the relation does not accept null. |
| A `HasOne` release touching more than one row | `PersistenceException`: unexpected related rows. |
| The database refuses the write | `PersistenceException` wrapping the driver's exception as `previous`. |

## Not yet supported

- **Cascades.** Deleting an entity does not touch what it relates to.
- **Writing a whole graph.** `insert()` writes one row; insert the related
  entity yourself first.
- **Reassigning a `HasOne`** in one call. Dissociate, then associate.
