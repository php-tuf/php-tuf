from fixtures.builder import FixtureBuilder


def build():
    # Set up a repo using `path_hash_prefixes`, which is currently not supported.
    fixture = FixtureBuilder('TUFTestFixtureUnsupportedDelegation')\
        .create_target('testtarget.txt')\
        .publish(with_client=True)

    # Delegate to an unclaimed target-signing key
    fixture.delegate('unsupported_target', ['unsupported_*.txt'], path_hash_prefixes= ['ab34df13'])\
        .create_target('unsupported_target.txt', signing_role='unsupported_target')\
        .publish()
