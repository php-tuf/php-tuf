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

## Resources

* Python TUF
  * [Code Documentation: Main Index](https://github.com/theupdateframework/tuf/blob/develop/tuf/README.md)
  * [CLI](https://github.com/theupdateframework/tuf/blob/develop/docs/CLI.md)
  * [Python API Readme](https://github.com/theupdateframework/tuf/blob/develop/tuf/client/README.md)
