# PHP-TUF

PHP-TUF is a PHP implementation of [The Update Framework 
(TUF)](https://theupdateframework.io/) to provide signing and verification for 
secure PHP application updates. [Read the TUF 
specification](https://github.com/theupdateframework/specification/blob/master/tuf-spec.md) 
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

- Minimum required PHP version: 7.2
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

@todo More detailed instructions.

### Server environment setup for the Python TUF CLI

1. Install OS-level dependencies:
   - On Fedora 33:

         sudo dnf install pipenv python3-devel libffi-devel
         
   - On Ubuntu 20.10:
   
         sudo apt-get install pipenv python3-dev libffi-dev

1. Configure the virtual environment:

       pipenv --three install

1. Launch a shell within the virtual environment:

       pipenv shell

## Code style

The code generally follows PSR-2 with some additional formatting rules for
code documentation and array formatting.

- Run `composer phpcs` to check for code style compliance.
- Run `composer phpcs-ci` to check only coding standards that will be hard
  blockers for a merge.

## Testing

### Test fixtures generation

1. Install the Python TUF implementation and enable the pipenv:

       pipenv install
       pipenv shell

1. Initialize the repository and add/sign a target:

       python generate_fixtures.py

1. Fixtures should appear in `fixtures/`.

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
* [TUF Specification](https://github.com/theupdateframework/specification/blob/master/tuf-spec.md)
* [PIP + TUF Integration](https://www.python.org/dev/peps/pep-0458/)
