# For instructions on using this script, please see the README.

from tuf import repository_tool as rt
import os
import shutil
from unittest import mock
import json
import fixtures.TUFTestFixtureSimple
import fixtures.TUFTestFixtureAttackRollback
import fixtures.TUFTestFixtureDelegated
import fixtures.TUFTestFixtureNestedDelegated
import fixtures.TUFTestFixtureUnsupportedDelegation
import fixtures.TUFTestFixtureNestedDelegatedErrors

# This file largely derives from the TUF tutorial:
# https://github.com/theupdateframework/tuf/blob/develop/docs/TUTORIAL.md


class TUFTestFixtureBase:
    """Base class for TUF test fixtures"""

    next_keypair_index = 0

    def __init__(self):
        # Initialize fixtures/ if missing and make it the working directory.
        fixtures_dir = os.path.join(os.path.dirname(
            os.path.realpath(__file__)), 'fixtures')
        if not os.path.exists(fixtures_dir):
            os.mkdir(fixtures_dir)

        self.my_fixtures_dir = os.path.join(fixtures_dir, type(self).__name__)
        print('Building fixtures at {}'.format(self.my_fixtures_dir))

        # Clean up previous fixtures.
        print('Deleting {}'.format(self.my_fixtures_dir))
        if os.path.isdir(self.my_fixtures_dir):
            shutil.rmtree(self.my_fixtures_dir + '/')

        os.mkdir(self.my_fixtures_dir)
        # os.chdir(self.my_fixtures_dir)

        # Create a basic TUF repository.
        self.tufrepo_dir = os.path.join(self.my_fixtures_dir, 'server')
        print('Initializing repo at {}'.format(self.tufrepo_dir))
        self.repository = rt.create_new_repository(
            self.tufrepo_dir, type(self).__name__)
        self.repository.status()
        self._initialize_basic_roles()
        # self.repository.status()
        print('Initialized repo at {}'.format(self.tufrepo_dir))

    def write_and_add_target(self, filename, signing_target=None):
        targets_dir = os.path.join(self.tufrepo_dir, 'targets')
        # if not os.path.exists(targets_dir):
        #    os.mkdir(targets_dir)

        print(targets_dir)

        with open(os.path.join(targets_dir, filename), 'w') as targetfile:
            targetfile.write('Contents: ' + filename)

        list_of_targets = [filename]

        if signing_target is None:
            self.repository.targets.add_targets(list_of_targets)
        else:
            self.repository.targets(signing_target).add_target(filename)
            self.repository.mark_dirty([signing_target])

        self.repository.mark_dirty(['snapshot', 'targets', 'timestamp'])

    def write_and_import_keypair(self, name_dst):
        # Identify the paths for the next pre-generated keypair.
        pathpriv_src = os.path.join(os.path.dirname(os.path.realpath(
            __file__)), 'fixtures', 'keys', '{}_key'.format(self.next_keypair_index))
        pathpub_src = '{}.pub'.format(pathpriv_src)
        self.next_keypair_index += 1

        print('Using key {} for {}'.format(pathpriv_src, name_dst))

        # Load the keys into TUF.
        public_key = rt.import_ed25519_publickey_from_file(pathpub_src)
        private_key = rt.import_ed25519_privatekey_from_file(
            pathpriv_src, password='pw')
        return (public_key, private_key)

    def delegate_role_with_file(self, delegator_role, delegated_role_name, paths, target_file):
        self.delegate_role(delegated_role_name, delegator_role, paths)
        self.write_and_add_target(target_file, delegated_role_name)

    def delegate_role(self, delegated_role_name, delegator_role, paths):
        (public_key, private_key) = self.write_and_import_keypair(
            delegated_role_name)
        delegator_role.delegate(
            delegated_role_name, [public_key], paths)
        delegator_role(delegated_role_name).load_signing_key(
            private_key)

    def _initialize_basic_roles(self):
        # Create and Import Keypairs
        # Public keys are stored to class attributes to allow revocation.
        (self.public_root_key, private_root_key) = self.write_and_import_keypair('root')
        (self.public_targets_key,
         private_targets_key) = self.write_and_import_keypair('targets')
        (self.public_snapshots_key,
         private_snapshots_key) = self.write_and_import_keypair('snapshot')
        (self.public_timestamps_key,
         private_timestamps_key) = self.write_and_import_keypair('timestamp')

        # Bootstrap Repository
        self.repository.root.add_verification_key(self.public_root_key)
        self.repository.root.load_signing_key(private_root_key)
        # Add additional roles
        self.repository.targets.add_verification_key(self.public_targets_key)
        self.repository.targets.load_signing_key(private_targets_key)
        self.repository.snapshot.add_verification_key(
            self.public_snapshots_key)
        self.repository.snapshot.load_signing_key(private_snapshots_key)
        self.repository.timestamp.add_verification_key(
            self.public_timestamps_key)
        self.repository.timestamp.load_signing_key(private_timestamps_key)
        self.repository.status()
        # Make it so (consistently)
        self.repository.mark_dirty(
            ['root', 'snapshot', 'targets', 'timestamp'])

    def write_and_publish_repository(self, export_client=False):
        self.repository.writeall(consistent_snapshot=True)
        # Publish the metadata
        staging_dir = os.path.join(self.tufrepo_dir, 'metadata.staged')
        live_dir = os.path.join(self.tufrepo_dir, 'metadata')
        shutil.copytree(staging_dir, live_dir, dirs_exist_ok=True)

        if export_client:
            client_tufrepo_dir = os.path.join(
                self.my_fixtures_dir, 'client')
            if os.path.exists(client_tufrepo_dir):
                shutil.rmtree(client_tufrepo_dir + '/')
            rt.create_tuf_client_directory(
                self.tufrepo_dir, client_tufrepo_dir)


class TUFTestFixtureSimple(TUFTestFixtureBase):
    def __init__(self):
        super().__init__()
        self.write_and_add_target('testtarget.txt')
        self.write_and_publish_repository(export_client=True)


class TUFTestFixtureThresholdTwo(TUFTestFixtureBase):
    def __init__(self):
        super().__init__()
        (public_timestamp_key_2, private_timestamp_key_2) = self.write_and_import_keypair(
            'timestamp_2')
        self.repository.timestamp.add_verification_key(public_timestamp_key_2)
        self.repository.timestamp.load_signing_key(private_timestamp_key_2)
        self.repository.timestamp.threshold = 2
        self.repository.mark_dirty(['timestamp'])
        self.write_and_publish_repository(export_client=True)


class TUFTestFixtureThresholdTwoAttack(TUFTestFixtureThresholdTwo):
    def __init__(self):
        super().__init__()
        timestamp_path = os.path.join(self.tufrepo_dir, "metadata", "timestamp.json")
        self.repository.mark_dirty(["timestamp"])
        self.write_and_publish_repository(export_client=True)

        # By exporting the repo but not the client, this gives us a new revision
        # that's ready to alter. If we alter a version the client is already
        # aware of, it may not pick up this new, altered version.
        self.repository.mark_dirty(["timestamp"])
        self.write_and_publish_repository(export_client=False)
        with open(timestamp_path) as timestamp_fd:
            timestamp = json.load(timestamp_fd)

        first_sig = timestamp["signatures"][0]
        timestamp["signatures"] = [first_sig, first_sig]

        with open(timestamp_path, "w") as timestamp_fd:
            json.dump(timestamp, timestamp_fd, indent=1)

        # We could also alter the versioned (N.timestamp.json), but the spec
        # considers these as optional, so we can expect this alteration to be
        # sufficient.


@mock.patch('time.time', mock.MagicMock(return_value=1577836800))
def generate_fixtures():
    # Fixtures generated with old method.
    # TODO: covert all fixtures to use new FixtureBuilder class and delete
    # classes above when all have been converted.
    TUFTestFixtureThresholdTwo()
    TUFTestFixtureThresholdTwoAttack()

    # Fixtures generated with new FixtureBuilder class.
    fixtures.TUFTestFixtureSimple.build()
    fixtures.TUFTestFixtureAttackRollback.build()
    fixtures.TUFTestFixtureDelegated.build()
    fixtures.TUFTestFixtureNestedDelegated.build()
    fixtures.TUFTestFixtureUnsupportedDelegation.build()
    fixtures.TUFTestFixtureNestedDelegatedErrors.build()


generate_fixtures()
