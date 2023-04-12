import string

from fixtures.builder import FixtureBuilder

def build():
    builder = FixtureBuilder('HashedBins', { 'use_snapshot_length': True })\
        .publish(with_client=True)

    list_of_targets = []
    for c in list(string.ascii_lowercase):
        name = c + '.txt'
        builder.create_target(name, signing_role=None)
        list_of_targets.append(name)

    public_key, private_key = builder._import_key()

    builder.repository.targets.delegate_hashed_bins(list_of_targets, [public_key], 8)

    for name in list_of_targets:
        builder.repository.targets.add_target_to_bin(name, 8)

    for role in builder.repository.targets.get_delegated_rolenames():
        builder.repository.targets(role).load_signing_key(private_key)

    builder.invalidate()
    builder.publish()
