import string

from fixtures.builder import FixtureBuilder

def build():
    builder = FixtureBuilder('HashedBins', { 'use_snapshot_length': True })\
        .publish(with_client=True)

    public_key, private_key = builder._import_key()

    builder.repository.targets.delegate_hashed_bins([], [public_key], 8)

    for c in list(string.ascii_lowercase):
        name = c + '.txt'
        builder.create_target(name, signing_role=None)
        builder.repository.targets.add_target_to_bin(name, 8)

    builder.invalidate()
    builder.publish()
