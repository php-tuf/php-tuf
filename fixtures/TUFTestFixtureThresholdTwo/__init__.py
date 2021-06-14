from fixtures.builder import FixtureBuilder


def build():
    fixture = FixtureBuilder('TUFTestFixtureThresholdTwo')\
        .add_key('timestamp')
    fixture._role('timestamp').threshold = 2
    fixture.repository.mark_dirty(['timestamp'])
    fixture.publish(with_client=True)
