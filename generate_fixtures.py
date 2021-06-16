# For instructions on using this script, please see the README.

from unittest import mock

from fixtures import (
    TUFTestFixtureSimple,
    TUFTestFixtureAttackRollback,
    TUFTestFixtureDelegated,
    TUFTestFixtureNestedDelegated,
    TUFTestFixtureUnsupportedDelegation,
    TUFTestFixtureNestedDelegatedErrors,
    TUFTestFixtureThresholdTwo,
    TUFTestFixtureThresholdTwoAttack,
    TUFTestFixtureTerminatingDelegation,
    TUFTestFixtureTopLevelTerminating,
    TUFTestFixtureNestedTerminatingNonDelegatingDelegation
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
    TUFTestFixtureTerminatingDelegation.build()
    TUFTestFixtureTopLevelTerminating.build()
    TUFTestFixtureNestedTerminatingNonDelegatingDelegation.build()


generate_fixtures()
