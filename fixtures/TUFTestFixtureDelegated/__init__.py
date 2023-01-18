from fixtures.builder import FixtureBuilder

import os


def build():
    variants = {
        'consistent': True,
        'inconsistent': False
    }
    for suffix, consistent in variants.items():
        name = os.path.join('TUFTestFixtureDelegated', suffix)
        fixture = FixtureBuilder(name)\
            .create_target('testtarget.txt')\
            .publish(with_client=True, consistent=consistent)\
            .delegate('unclaimed', ['level_1_*.txt'])\
            .create_target('level_1_target.txt', signing_role='unclaimed')\
            .publish(with_client=True, consistent=consistent)
        # === Point of No Return ===
        # Past this point, we don't re-export the client. This supports testing the
        # client's own ability to pick up and trust new data from the repository.
        fixture.add_key('targets')\
            .add_key('snapshot')\
            .invalidate()\
            .publish(consistent=consistent)\
            .revoke_key('targets')\
            .revoke_key('snapshot')\
            .invalidate()\
            .publish(consistent=consistent)
