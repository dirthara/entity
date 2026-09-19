# Security Policy

## Supported versions

The package is pre-1.0. Only the latest release line receives fixes; there are
no backports to earlier ones.

| Version | Supported |
| --- | --- |
| 0.2.x | Yes |
| Older | No |

## Reporting a vulnerability

Report vulnerabilities privately using GitHub's
[Report a vulnerability](https://github.com/dirthara/entity/security/advisories/new)
form. Do not disclose vulnerabilities in public issues or pull requests.

Include what you have:

- Which version you found it in.
- What an attacker can do, and what they need to already have to do it.
- The smallest entity class and code that shows the problem.
- The database and PDO driver, if the behaviour depends on them.

You will get an acknowledgement that the report was received and an assessment
once the report has been reproduced. If a fix is warranted, the advisory is
published together with the release that contains it, crediting you unless you
ask otherwise.

## Scope

The package turns a class into queries and rows back into objects. Table and
column names come from a class and its attributes rather than from a bound
parameter, and a column's contents are decoded into PHP values. Everything that
gets past those two boundaries is in scope, including:

- A table name, column name or converter-produced value reaching SQL in a way
  that lets it change a statement's structure, rather than being bound or
  quoted.
- A column's contents becoming code or an object during hydration, such as an
  `unserialize` that instantiates a class the application did not expect.
- A store reading or writing a row that its identifier does not match, or a
  write reaching a table other than the entity's.
- Credentials or entity values appearing in exception messages, exception
  context, dumps, or stack traces.

Out of scope:

- Raw SQL an application runs through `dirthara/database` itself. This package
  documents the store and query API as the alternative.
- A converter of your own that decodes a column unsafely. The
  `TypeConverter` contract is the boundary; what an implementation does with a
  value is the implementation's responsibility.
- Bugs in PHP, PDO, or a database driver extension. Report those upstream; if
  the package can defend against one, that is worth reporting here too.
- What an application chooses to store in an entity, and how sensitive it is.

## Hardening notes

The `serialized` converter passes `allowed_classes: false`, so a column can only
ever come back as arrays and scalars. There is no option to relax it:
unserializing objects out of a database row is how a row becomes code execution.
A converter of your own that reaches for `unserialize` should do the same.

Table and column names are validated where they are built and are never bound as
parameters, because they cannot be. A naming strategy of your own returns names
that end up in SQL, so it must not pass through anything an entity's author did
not write.

Exception context is written to logs. It carries the entity class, the property,
the column and the operation. A conversion failure also records the value it
could not convert, so do not map a secret to a column whose converter can fail.
Keep that split in your own converters and exceptions.
