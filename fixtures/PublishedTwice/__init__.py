from fixtures.builder import FixtureBuilder


def build(rotate_keys=False):
    name = 'PublishedTwice'
    if rotate_keys is True:
        name += 'WithRotatedKeys'

    fixture = FixtureBuilder(name).publish(with_client=True)
    if rotate_keys is True:
        fixture.add_key('timestamp').revoke_key('timestamp', key_index=0)
    fixture.publish()
