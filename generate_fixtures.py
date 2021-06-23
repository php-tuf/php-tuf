# For instructions on using this script, please see the README.
import os

from unittest import mock
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

def GetHashofDirs(directory, verbose=0):
    if not os.path.exists (directory):
        return -1

    for root, dirs, files in os.walk(directory):
        for names in files:
            filepath = os.path.join(root, names)
            print(filepath)



GetHashofDirs('fixtures')
