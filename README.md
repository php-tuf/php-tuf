# PHP-TUF

![build](https://github.com/php-tuf/php-tuf/actions/workflows/build.yml/badge.svg)

## IMPORTANT
PHP-TUF is in a pre-release state and is not considered a complete or secure version of the TUF framework.
It should currently only be used for testing, development and feedback.

*Do not use in production for secure target downloads!!*

PHP-TUF is a PHP implementation of [The Update Framework
(TUF)](https://theupdateframework.io/) to provide signing and verification for
secure PHP application updates. [Read the TUF
specification](https://theupdateframework.github.io/specification/v1.0.19)
for more information on how TUF is intended to work and the security it
provides.

PHP-TUF project development is primarily focused on supporting secure automated
updates for PHP CMSes, although it should also work for any PHP application or
Composer project. Contributing projects:

- [Drupal](https://www.drupal.org/)
- [TYPO3](https://typo3.org/)
- [Joomla](https://www.joomla.org/)

## PHP-TUF client requirements

The PHP-TUF client is designed to provide TUF verification to PHP applications
for target signatures.

- Minimum required PHP version: 8.0
- Requires `ext-json`
- The `paragonie/sodium_compat` dependency provides a polyfill for the Sodium
  cryptography library; however, installing `ext-sodium` is recommended for
  better performance and security.

## PHP-TUF development requirements

We recommend using the [default CLI
implementation](https://github.com/theupdateframework/tuf/blob/develop/docs/CLI.md)
(a Python application) to generate keys and signatures as a part of your
project's release creation process. This will require:
- Python 3.9+
- PIP 19+

@todo More detailed instructions. https://github.com/php-tuf/php-tuf/issues/170

### Server environment setup for the Python TUF CLI

1. Install OS-level dependencies:
   - On Fedora 33:

         sudo dnf install pipenv python3-devel libffi-devel

   - On Ubuntu 20.10:

         sudo apt-get install pipenv python3-dev libffi-dev

2. Configure the virtual environment:

       pipenv install

## Code style

The code generally follows PSR-2 with some additional formatting rules for
code documentation and array formatting. Run PHPCS to check for code style
compliance:

     composer phpcs

## Testing

### Test fixtures generation

Run the following command:

       composer fixtures

Fixtures should appear in `fixtures/`.

### Running the PHP-TUF tests

1. Ensure you have all required dependencies by running `composer install`.
2. Run `composer test` at the project's root.

### Leveraging test fixtures directly

1. From `fixtures/*/tufrepo`:

       python3 -m http.server 8001

1. From `fixtures/*/tufclient`:

       mkdir -p tuftargets
       curl http://localhost:8001/targets/testtarget.txt > tuftargets/testtarget.txt
       client.py --repo http://localhost:8001 testtarget.txt
       # A 404 is expected for N.root.json unless a key has been rotated.

## Dependency policies and information

To provide a lightweight, reliable, and secure client, external dependencies
are carefully limited. Any proposed dependency additions (and those
dependencies' dependencies) should undergo the [Drupal core dependency
evaluation process](https://www.drupal.org/core/dependencies#criteria).

For evaluations and policies of current dependencies, see the [PHP-TUF
dependency information](DEPENDENCIES.md).

## Resources

* [PHP-TUF wiki](https://github.com/php-tuf/php-tuf/wiki)
* Python TUF
  * [Code Documentation: Main Index](https://github.com/theupdateframework/tuf/blob/develop/tuf/README.md)
  * [CLI](https://github.com/theupdateframework/tuf/blob/develop/docs/CLI.md)
  * [Python API Readme](https://github.com/theupdateframework/tuf/blob/develop/tuf/client/README.md)
* [TUF Specification v1.0.19](https://theupdateframework.github.io/specification/v1.0.19)
* [PIP + TUF Integration](https://www.python.org/dev/peps/pep-0458/)
