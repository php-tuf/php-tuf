"""
Builds a test fixture that has no targets, but is published twice, with a rotated
timestamp key in the second version.
"""
from fixtures.builder import FixtureBuilder


def build():
    FixtureBuilder('RotatedKeys')\
        .publish(with_client=True)\
        .add_key('timestamp')\
        .revoke_key('timestamp', key_index=0)\
        .publish()
