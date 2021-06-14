from fixtures.builder import FixtureBuilder


def build():
    fixture = FixtureBuilder('TUFTestFixtureThresholdTwoAttack')\
        .add_key('timestamp')
    fixture._role('timestamp').threshold = 2
    fixture.repository.mark_dirty(['timestamp'])
    fixture.publish(with_client=True)
    fixture.repository.mark_dirty(['timestamp'])
    fixture.publish(with_client=True)

    # By exporting the repo but not the client, this gives us a new revision
    # that's ready to alter. If we alter a version the client is already
    # aware of, it may not pick up this new, altered version.
    fixture.repository.mark_dirty(['timestamp'])
    fixture.publish()

    timestamp = fixture.read('timestamp.json')
    signature = timestamp['signatures'][0].copy()
    timestamp["signatures"] = [signature, signature]

    fixture.write('timestamp.json', timestamp)

    # We could also alter the versioned (N.timestamp.json), but the spec
    # considers these as optional, so we can expect this alteration to be
    # sufficient.
