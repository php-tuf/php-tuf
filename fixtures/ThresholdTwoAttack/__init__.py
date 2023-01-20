from fixtures.builder import FixtureBuilder

import os


def build():
    _build(True)
    _build(False)

def _build(consistent):
    if consistent is True:
        suffix = 'consistent'
    else:
        suffix = 'inconsistent'

    name = os.path.join('ThresholdTwoAttack', suffix)

    fixture = FixtureBuilder(name)\
        .add_key('timestamp')
    fixture._role('timestamp').threshold = 2
    fixture.repository.mark_dirty(['timestamp'])
    fixture.publish(with_client=True, consistent=consistent)
    fixture.repository.mark_dirty(['timestamp'])
    fixture.publish(with_client=True, consistent=consistent)

    # By exporting the repo but not the client, this gives us a new revision
    # that's ready to alter. If we alter a version the client is already
    # aware of, it may not pick up this new, altered version.
    fixture.repository.mark_dirty(['timestamp'])
    fixture.publish(consistent=consistent)

    timestamp = fixture.read('timestamp.json')
    signature = timestamp['signatures'][0].copy()
    timestamp["signatures"] = [signature, signature]

    fixture.write('timestamp.json', timestamp)

    # We could also alter the versioned (N.timestamp.json), but the spec
    # considers these as optional, so we can expect this alteration to be
    # sufficient.
