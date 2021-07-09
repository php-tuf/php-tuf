from fixtures.builder import FixtureBuilder


def build(rotate_keys=None):
    """
    Generates a TUF test fixture that publishes twice -- once on the client,
    and twice on the server -- and, in between those two publications, can
    optionally rotate the keys of a given role.
    """
    name = 'PublishedTwice'
    if rotate_keys is not None:
        name += 'WithRotatedKeys_' + rotate_keys

    fixture = FixtureBuilder(name).publish(with_client=True)
    if rotate_keys is not None:
        fixture.add_key(rotate_keys)\
            .revoke_key(rotate_keys, key_index=0)
    fixture.publish()
