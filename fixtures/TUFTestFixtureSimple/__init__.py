from fixtures.builder import FixtureBuilder

import os


def build():
    variants = {
        'consistent': True,
        'inconsistent': False
    }
    for suffix, consistent in variants.items():
        name = os.path.join('TUFTestFixtureSimple', suffix)
        FixtureBuilder(name)\
            .create_target('testtarget.txt')\
            .publish(with_client=True, consistent=consistent)
