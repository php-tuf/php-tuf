# For instructions on using this script, please see the README.

from unittest import mock
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


generate_fixtures()

# Create hash of generated fixtures directory.
hash_file = open("fixtures-hash.txt", "w")
n = hash_file.write("hash:" + dirhash('fixtures', 'md5'))
hash_file.close()
