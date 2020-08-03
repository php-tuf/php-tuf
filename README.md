# PHP-TUF

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
