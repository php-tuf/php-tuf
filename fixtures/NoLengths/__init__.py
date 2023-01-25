from fixtures.builder import ConsistencyVariantFixtureBuilder


def build():
    """
    Generates a TUF test fixture that publishes twice -- once on the client,
    and twice on the server -- without any length information in the timestamp
    or snapshot metadata.
    """
    ConsistencyVariantFixtureBuilder('NoLengths', { 'use_timestamp_length': False, 'use_snapshot_length': False })\
    .publish(with_client=True)\
    .publish()
