from fixtures.builder import ConsistencyVariantFixtureBuilder


def build():
    builder = ConsistencyVariantFixtureBuilder('TUFTestFixtureThresholdTwo')\
        .add_key('timestamp')
    for fixture in builder.fixtures:
        fixture._role('timestamp').threshold = 2
        fixture.repository.mark_dirty(['timestamp'])
    builder.publish(with_client=True)
