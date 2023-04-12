import string

from fixtures.builder import FixtureBuilder

def build():
    builder = FixtureBuilder('HashedBins', { 'use_snapshot_length': True })\
        .publish(with_client=True)

    public_key = builder._keys['targets']['public'][0]
    private_key = builder._keys['targets']['private'][0]

    builder.repository.targets.delegate_hashed_bins([], [public_key])

    for c in list(string.ascii_lowercase):
        name = c + '.txt'
        builder.create_target(name, signing_role=None)
        builder.repository.targets.add_target_to_bin(name)

    for name in builder.repository.targets.get_delegated_rolenames():
        builder.repository.targets(name).load_signing_key(private_key)
        builder.repository.targets(name).add_verification_key(public_key)

    builder.invalidate()
    builder.publish()
