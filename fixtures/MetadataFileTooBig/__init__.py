import json
import os

from fixtures.builder import ConsistencyVariantFixtureBuilder


def build(file, authority):
    """
    Generates a TUF test fixture that publishes twice -- once on the client,
    and twice on the server -- and makes a metadata file whose size is stated
    in another, authoritative metadata file bigger than expected. For example,
    if the `file` argument is `snapshot`, and `authority` is `timestamp`, then
    snapshot.json will be replaced with a garbage file bigger than stated in
    timestamp.json.
    """
    name = 'MetadataFileTooBig_' + file

    builder = ConsistencyVariantFixtureBuilder(name, tuf_arguments={ 'use_timestamp_length': True, 'use_snapshot_length': True })\
    .publish(with_client=True)\
    .publish()

    garbage_file_name = file + '.json'

    for fixture in builder.fixtures:
        metadata_dir = os.path.join(fixture.dir, 'server', 'metadata')
        authority_file = os.path.join(metadata_dir, authority + '.json')
        garbage_file = open(os.path.join(metadata_dir, garbage_file_name), 'w')

        with open(authority_file, 'r') as f:
            authority_data = json.load(f)
            garbage_file.write("Garbage!" * authority_data['signed']['meta'][garbage_file_name]['length'])
        garbage_file.close()
