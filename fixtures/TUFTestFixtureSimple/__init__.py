from fixtures.builder import FixtureBuilder

import os


def build():
    name = os.path.join('TUFTestFixtureSimple', 'consistent')
    FixtureBuilder(name)\
        .create_target('testtarget.txt')\
        .publish(with_client=True, consistent=True)

    name = os.path.join('TUFTestFixtureSimple', 'inconsistent')
    FixtureBuilder(name)\
        .create_target('testtarget.txt')\
        .publish(with_client=True, consistent=False)
