<p align="center">
  <img src="logo-no-bg.png" alt="Dirthara" width="480">
</p>

# Dirthara Entity

Entities for the Dirthara framework. Describe a class with attributes and read or
write its table through a typed store, with every value crossing the boundary
through a converter.

## Installation

```sh
composer require dirthara/entity
```

The package requires PHP 8.5,
[`dirthara/database`](https://github.com/dirthara/database) `^0.1` and
[`dirthara/collection`](https://github.com/dirthara/collection) `^0.1`, which
Composer installs for you. The database package owns the connections, drivers and
query builder; this one maps classes onto them. It never touches PDO itself, but
that package does, so each database needs its own PDO extension: `pdo_mysql`,
`pdo_pgsql`, `pdo_sqlite`, or `pdo_sqlsrv`.

Usage documentation lives in [`docs`](docs/intro.md), which is published as a
Docusaurus site by a separate package.

## Docker development environment

Requires Docker with Docker Compose. The development image provides PHP 8.5 CLI,
Composer 2.10.3, Mago 1.47.3, Xdebug, and a PDO driver for every database the
package supports: `pdo_sqlite`, `pdo_mysql`, `pdo_pgsql`, and `pdo_sqlsrv`.

```sh
git clone git@github.com:dirthara/entity.git
cd entity
LOCAL_UID=$(id -u) LOCAL_GID=$(id -g) docker compose up -d --build php
docker compose exec php composer install
```

The container runs as the non-root `developer` user. The build arguments
`LOCAL_UID` and `LOCAL_GID` default to 1000; the command above uses your host IDs
so generated files remain editable. Set `PHP_VERSION` to override the default
8.5 image. Rebuild when the Dockerfile or build arguments change.

`docker compose up -d php` also starts PostgreSQL, MySQL, and SQL Server and
waits until each reports healthy, because the tests run against every driver the
package supports. The first start pulls roughly a gigabyte of images, and SQL
Server takes around thirty seconds to accept connections. The SQL Server image is
published for amd64 only, so its tests skip on an arm64 host.

Open a shell or stop the environment with:

```sh
docker compose exec php bash
docker compose down
```

## Tests

```sh
docker compose exec php composer test
```

Tests belong in `tests`, under `Dirthara\Entity\Tests`. Source belongs in
`src`, under `Dirthara\Entity`.

Most of the suite maps classes and asserts on the result, which needs no server.
Behaviour that needs a real database belongs in `tests/Integration`, where one
conformance suite runs against every driver: writing a row, reading it back, and
checking what each driver reports for a converted column. What a driver answers
with for a boolean or a float cannot be judged without one.

SQLite runs in memory and always runs; the PostgreSQL, MySQL, and SQL Server
suites skip when their PDO driver is missing, and read their connection from
`DIRTHARA_POSTGRES_*`, `DIRTHARA_MYSQL_*`, and `DIRTHARA_SQLSRV_*` (`_HOST`,
`_PORT`, `_DATABASE`, `_USERNAME`, `_PASSWORD`), defaulting to the services in
`compose.yaml`.

## Code quality

Run the same checks as CI:

```sh
docker compose exec php composer ci
```

Run individual checks:

```sh
docker compose exec php composer fmt-check
docker compose exec php composer lint
docker compose exec php composer analyze
docker compose exec php composer guard
```

`composer mago` runs the formatting, import-order, lint, analysis, and
architecture checks. `composer ci` also runs tooling tests, unit tests, and the
coverage gate. The architecture rules in `mago.toml` keep the mapping, hydration,
type, and naming layers free of any dependency on the database packages, and
require every class outside `EntityException` to be final.

Apply formatting and import sorting with `composer fmt`, or include automatic
lint fixes with `composer cs`:

```sh
docker compose exec php composer fmt
docker compose exec php composer cs
```

`composer cs` includes potentially unsafe lint fixes; review its changes.

Run coverage separately with:

```sh
docker compose exec php composer test-coverage
docker compose exec php composer coverage
```

Xdebug is inactive by default and enabled for the coverage run. The report is
written to `build/coverage/clover.xml`. The gate requires 100% line coverage of
`src` and lists uncovered lines.

## Contributing

Each supported version has its own branch, beginning with `0.1`; there is no
`main`. See [CONTRIBUTING.md](CONTRIBUTING.md) for branching, release, and pull
request requirements, and [AGENTS.md](AGENTS.md) for agent instructions.

## Security

Report vulnerabilities through GitHub's private advisory form. See
[SECURITY.md](SECURITY.md) for the reporting process and scope.

## License

Copyright (c) 2026 Dirthara. Released under the [MIT License](LICENSE).
