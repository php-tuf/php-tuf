# For instructions on using this script, please see the README.

from unittest import mock
import shutil
import glob
import os

from fixtures import (
    Simple,
    AttackRollback,
    Delegated,
    NestedDelegated,
    UnsupportedDelegation,
    NestedDelegatedErrors,
    ThresholdTwo,
    ThresholdTwoAttack,
    TerminatingDelegation,
    TopLevelTerminating,
    NestedTerminatingNonDelegatingDelegation,
    3LevelDelegation,
    PublishedTwice
)


@mock.patch('time.time', mock.MagicMock(return_value=1577836800))
def generate_fixtures():
    Simple.build()
    AttackRollback.build()
    Delegated.build()
    NestedDelegated.build()
    UnsupportedDelegation.build()
    NestedDelegatedErrors.build()
    ThresholdTwo.build()
    ThresholdTwoAttack.build()
    TerminatingDelegation.build()
    TopLevelTerminating.build()
    NestedTerminatingNonDelegatingDelegation.build()
    3LevelDelegation.build()
    PublishedTwice.build()
    PublishedTwice.build(rotate_keys='timestamp')
    PublishedTwice.build(rotate_keys='snapshot')


# Remove all previous fixtures.
for f in glob.glob("fixtures/**/client"):
    shutil.rmtree(f)
for f in glob.glob("fixtures/**/server"):
    shutil.rmtree(f)
# Delete hash files to ensure they are generated again.
for f in glob.glob("fixtures/**/hash.txt"):
    os.remove(f)
generate_fixtures()

