# For instructions on using this script, please see the README.

from tuf import repository_tool as rt
import os
import shutil
from unittest import mock

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
        self.tufrepo_dir = os.path.join(self.my_fixtures_dir, 'tufrepo')
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
            targetfile.write(filename)

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
            __file__)), 'fixture_keys', '{}_key'.format(self.next_keypair_index))
        pathpub_src = '{}.pub'.format(pathpriv_src)
        self.next_keypair_index += 1

        print('Using key {} for {}'.format(pathpriv_src, name_dst))

        # Load the keys into TUF.
        public_key = rt.import_ed25519_publickey_from_file(pathpub_src)
        private_key = rt.import_ed25519_privatekey_from_file(
            pathpriv_src, password='pw')
        return (public_key, private_key)

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
        self.repository.writeall(consistent_snapshot=True)

    def write_and_publish_repository(self, export_client=False):
        self.repository.writeall(consistent_snapshot=True)
        # Publish the metadata
        staging_dir = os.path.join(self.tufrepo_dir, 'metadata.staged')
        live_dir = os.path.join(self.tufrepo_dir, 'metadata')
        shutil.copytree(staging_dir, live_dir, dirs_exist_ok=True)

        if export_client:
            client_tufrepo_dir = os.path.join(
                self.my_fixtures_dir, 'tufclient', 'tufrepo')
            if os.path.exists(client_tufrepo_dir):
                shutil.rmtree(client_tufrepo_dir + '/')
            rt.create_tuf_client_directory(
                self.tufrepo_dir, client_tufrepo_dir)


class TUFTestFixtureSimple(TUFTestFixtureBase):
    def __init__(self):
        super().__init__()
        self.write_and_add_target('testtarget.txt')
        self.write_and_publish_repository(export_client=True)


class TUFTestFixtureDelegated(TUFTestFixtureSimple):
    def __init__(self):
        super().__init__()

        # Delegate to an unclaimed target-signing key
        (public_unclaimed_key, private_unclaimed_key) = self.write_and_import_keypair(
            'targets_delegated')
        self.repository.targets.delegate(
            'unclaimed', [public_unclaimed_key], ['testunclaimed*.txt'])
        self.write_and_add_target('testunclaimedtarget.txt', 'unclaimed')
        self.repository.targets('unclaimed').load_signing_key(
            private_unclaimed_key)
        self.write_and_publish_repository(export_client=True)

        # === Point of No Return ===
        # Past this point, we don't re-export the client. This supports testing the
        # client's own ability to pick up and trust new data from the repository.
        # Generate new keys for target and snapshot roles.
        (public_targets_key_2, private_targets_key_2) = self.write_and_import_keypair(
            'targets2')
        (public_snapshots_key_2,
         private_snapshots_key_2) = self.write_and_import_keypair('snapshot2')
        # Add new verification keys.
        self.repository.targets.add_verification_key(public_targets_key_2)
        self.repository.targets.load_signing_key(private_targets_key_2)
        self.repository.snapshot.add_verification_key(public_snapshots_key_2)
        self.repository.snapshot.load_signing_key(private_snapshots_key_2)
        self.repository.status()
        # Write the updated repository data.
        self.repository.mark_dirty(
            ['root', 'snapshot', 'targets', 'timestamp'])
        self.write_and_publish_repository()
        # Revoke the older keys.
        self.repository.targets.remove_verification_key(
            self.public_targets_key)
        self.repository.snapshot.remove_verification_key(
            self.public_snapshots_key)
        self.repository.status()
        # Write the updated repository data.
        self.repository.mark_dirty(
            ['root', 'snapshot', 'targets', 'timestamp'])
        self.write_and_publish_repository()


@mock.patch('time.time', mock.MagicMock(return_value=1577836800))
def generate_fixtures():
    TUFTestFixtureSimple()
    TUFTestFixtureDelegated()


generate_fixtures()
