# PHP-TUF

## What is PHP-TUF?

## PHP-TUF server requirements

We recommend using the [default CLI implementation](https://github.com/theupdateframework/tuf/blob/develop/docs/CLI.md) (a Python application) to generate keys and signatures as a part of your project's release creation process.

@todo More detailed instructions.

## PHP-TUF client requirements

The PHP-TUF client is designed to provide TUF verification to PHP applications for target signatures.

- Minimum required PHP version: 7.2
- Requires `ext-json`
- The `paragonie/sodium_compat` dependency provides a polyfill for the Sodium
  cryptography library; however, installing `ext-sodium` is recommended for
  better performance and security.

## Running the tests
1. Ensure you have all required dependencies by running `composer install`.
2. Run `composer test` at the project's root.

## Code Style
Run `composer phpcs` to check for code style compliance. The code adheres to PSR-2 code standards.

## Environment Setup for Python TUF CLI

1. Install Python 3.8+ and PIP 19+ (not tested on earlier but may work).
1. Set up a virtual environment:

       python3 -m venv venv
       source venv/bin/activate

1. Install dependencies and TUF:

       pip install -r requirements.txt

## Test Fixtures Setup

1. Start a `fixtures` directory:

       mkdir fixtures

1. Initialize the repository and add/sign a target:

       repo.py --path=fixtures/ --init --consistent  # Defaults to Ed25519
       echo "Test File" > testtarget.txt
       repo.py --path=fixtures/ --add testtarget.txt

## Using Test Fixtures

1. From `fixtures/tufrepo`:

       python3 -m http.server 8001

1. From `fixtures/tufclient`:

       mkdir -p tuftargets
       curl http://localhost:8001/targets/testtarget.txt > tuftargets/testtarget.txt
       client.py --repo http://localhost:8001 testtarget.txt  # A 404 is expected for N.root.json unless a key has been rotated.

## Resources

* Python TUF
  * [Code Documentation: Main Index](https://github.com/theupdateframework/tuf/blob/develop/tuf/README.md)
  * [CLI](https://github.com/theupdateframework/tuf/blob/develop/docs/CLI.md)
  * [Python API Readme](https://github.com/theupdateframework/tuf/blob/develop/tuf/client/README.md)
* [TUF Specification](https://github.com/theupdateframework/specification/blob/master/tuf-spec.md)
* [PIP + TUF Integration](https://github.com/theupdateframework/pep-on-pypi-with-tuf)
