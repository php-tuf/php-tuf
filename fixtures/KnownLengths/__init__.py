from fixtures.builder import ConsistencyVariantFixtureBuilder


def build():
    """
    Generates a TUF test fixture that publishes twice -- once on the client,
    and twice on the server -- without length information for the timestamp
    and snapshot metadata.
    """
    ConsistencyVariantFixtureBuilder('KnownLengths', { 'use_timestamp_length': True, 'use_snapshot_length': True })\
    .publish(with_client=True)\
    .publish()
