Developing PHP-TUF
==================

This project uses [DDEV](https://ddev.com) to standardize its local development environment. To get started, make sure that you have the [latest release](https://github.com/ddev/ddev/releases) of DDEV [installed](https://ddev.com/get-started/).

## Initial setup

```
ddev start
ddev composer install
```

## Running tests

```
ddev composer fixtures
ddev composer test
```

To run a single test use PHPUnit's `--filter` option:
```
ddev exec phpunit ./tests --debug --filter=testEmptyStructuresAreEncodedAsObjects
```

### Checking code coverage

```
ddev xdebug
ddev composer coverage
```

## Linting and fixing

Linting for syntax, then style:
```
ddev composer lint
ddev composer phpcs
```

Fixing style errors that can be automatically fixed:
```
ddev composer phpcbf
```
