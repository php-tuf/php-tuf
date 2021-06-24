# For instructions on using this script, please see the README.

from unittest import mock
import shutil
import glob
from dirhash import dirhash
from fixtures import (
    TUFTestFixtureSimple,
    TUFTestFixtureAttackRollback,
    TUFTestFixtureDelegated,
    TUFTestFixtureNestedDelegated,
    TUFTestFixtureUnsupportedDelegation,
    TUFTestFixtureNestedDelegatedErrors,
    TUFTestFixtureThresholdTwo,
    TUFTestFixtureThresholdTwoAttack
)


@mock.patch('time.time', mock.MagicMock(return_value=1577836800))
def generate_fixtures():
    TUFTestFixtureSimple.build()
    TUFTestFixtureAttackRollback.build()
    TUFTestFixtureDelegated.build()
    TUFTestFixtureNestedDelegated.build()
    TUFTestFixtureUnsupportedDelegation.build()
    TUFTestFixtureNestedDelegatedErrors.build()
    TUFTestFixtureThresholdTwo.build()
    TUFTestFixtureThresholdTwoAttack.build()


# Remove all previous fixtures.
for f in glob.glob("fixtures/*/client"):
    shutil.rmtree(f)
for f in glob.glob("fixtures/*/server"):
    shutil.rmtree(f)
generate_fixtures()

# Create a hash for each generated fixtures directory.
hash_file = open("fixtures-hash.txt", "w")
fixtures = [
"TUFTestFixtureSimple",
"TUFTestFixtureAttackRollback",
"TUFTestFixtureDelegated",
"TUFTestFixtureNestedDelegated",
"TUFTestFixtureUnsupportedDelegation",
"TUFTestFixtureNestedDelegatedErrors",
"TUFTestFixtureThresholdTwo",
"TUFTestFixtureThresholdTwoAttack"
]
file_contents = ""
for fixture in fixtures:
    file_contents = file_contents + fixture + "-hash:" + dirhash('fixtures/' + fixture, 'md5') + "\n"

n = hash_file.write(file_contents)
hash_file.close()
