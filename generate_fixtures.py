# For instructions on using this script, please see the README.

from tuf import repository_tool as rt
import os
import shutil
from unittest import mock
import json

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


class TUFTestFixtureAttackRollback(TUFTestFixtureSimple):
    def __init__(self):
        super().__init__()
        backup_dir = self.tufrepo_dir + "_backup"
        shutil.copytree(self.tufrepo_dir, backup_dir, dirs_exist_ok=True)
        self.write_and_add_target('testtarget2.txt')
        self.write_and_publish_repository(export_client=True)
        shutil.rmtree(self.tufrepo_dir + '/')
        # Reset the client to previous state to simulate a rollback attack.
        shutil.copytree(backup_dir, self.tufrepo_dir, dirs_exist_ok=True)
        shutil.rmtree(backup_dir + '/')


class TUFTestFixtureDelegated(TUFTestFixtureSimple):
    def __init__(self):
        super().__init__()

        # Delegate to an unclaimed target-signing key
        (public_unclaimed_key, private_unclaimed_key) = self.write_and_import_keypair(
            'targets_delegated')
        self.repository.targets.delegate(
            'unclaimed', [public_unclaimed_key], ['level_1_*.txt'])
        self.write_and_add_target('level_1_target.txt', 'unclaimed')
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

class TUFTestFixtureUnsupportedDelegation(TUFTestFixtureSimple):
    # Sets up a repo using `path_hash_prefixes` which is currently not supported.
    def __init__(self):
        super().__init__()

        # Delegate to an unclaimed target-signing key
        (public_unclaimed_key, private_unclaimed_key) = self.write_and_import_keypair(
            'targets_delegated')
        self.repository.targets.delegate(
            'unsupported_target', [public_unclaimed_key], ['unsupported_*.txt'], path_hash_prefixes= ['ab34df13'])
        self.write_and_add_target('unsupported_target.txt', 'unsupported_target')
        self.repository.targets('unsupported_target').load_signing_key(
            private_unclaimed_key)
        self.write_and_publish_repository(export_client=False)

class TUFTestFixtureNestedDelegated(TUFTestFixtureDelegated):
    def __init__(self):
        super().__init__()
        level_1_delegation = self.repository.targets._delegated_roles.get('unclaimed')

        # Delegate from level_1_delegation to level_1 target-signing key
        (public_level_2_key, private_level_2_key) = self.write_and_import_keypair(
            'targets_level_2_delegated')

        # Add a delegation that matches the path pattern 'test_nested_*.txt'
        level_1_delegation.delegate(
            'level_2', [public_level_2_key], ['level_1_2_*.txt'])
        self.write_and_add_target('level_1_2_target.txt', 'level_2')
        level_1_delegation('level_2').load_signing_key(
            private_level_2_key)

        # Add a terminating delegation.
        level_1_delegation.delegate(
            'level_2_terminating', [public_level_2_key], ['level_1_2_terminating_*.txt'], terminating=True)
        self.write_and_add_target('level_1_2_terminating_findable.txt', 'level_2_terminating')
        level_1_delegation('level_2_terminating').load_signing_key(
            private_level_2_key)

        level_2_delegation = level_1_delegation._delegated_roles.get('level_2')

        # Delegate from level_1 to nested2_delegation delegation target-signing key
        (public_level_3_key, private_level_3key) = self.write_and_import_keypair(
            'targets_level_3_delegated')

        # Add a delegated role 'level_3' from role 'level_2'. For files in this delegation to be found
        # the 'paths' property must also be compatible with the 'paths' property of 'level_2'
        level_2_delegation.delegate(
            'level_3', [public_level_3_key], ['level_1_2_3_*.txt'])
        self.write_and_add_target('level_1_2_3_below_non_terminating_target.txt', 'level_3')
        level_2_delegation('level_3').load_signing_key(
            private_level_3key)

        # Add a delegation below the 'level_2_terminating' role.
        # Delegations from a terminating role are evaluated but delegations after a terminating delegation
        # are not.
        # See TUFTestFixtureNestedDelegatedErrors
        level_2_terminating_delegation = self.repository.targets._delegated_roles.get('level_2_terminating')
        (public_level_3_below_terminated_key, private_level_3_below_terminated_key) = self.write_and_import_keypair(
            'targets_level_3_below_terminated')
        level_2_terminating_delegation.delegate(
            'level_3_below_terminated', [public_level_3_below_terminated_key], ['level_1_2_terminating_3_*.txt'])
        self.write_and_add_target('level_1_2_terminating_3_target.txt', 'level_3_below_terminated')
        level_2_terminating_delegation('level_3_below_terminated').load_signing_key(
            private_level_3_below_terminated_key)

        self.write_and_publish_repository(export_client=False)

class TUFTestFixtureNestedDelegatedErrors(TUFTestFixtureNestedDelegated):
    def __init__(self):
        super().__init__()
        # Add a target that does not match the path for the delegation.
        self.write_and_add_target('level_a.txt', 'unclaimed')
        # Add a target that matches the path parent delegation but not the current delegation.
        self.write_and_add_target('level_1_3_target.txt', 'level_2')

        level_1_delegation = self.repository.targets._delegated_roles.get('unclaimed')

        # Add a target that does not match the delegation's paths.
        self.write_and_add_target('level_2_unfindable.txt', 'level_2_terminating')

        (public_level_2_after_terminating_key, private_level_2_after_terminating_key) = self.write_and_import_keypair(
            'targets_level_2_after_terminating')

        # Add a delegation after level_2_terminating which will not be evaluted.
        level_1_delegation.delegate(
            'level_2_after_terminating', [public_level_2_after_terminating_key], ['level_2_*.txt'])
        self.write_and_add_target('level_2_after_terminating_unfindable.txt', 'level_2_after_terminating')
        level_1_delegation('level_2').load_signing_key(
            private_level_2_after_terminating_key)

        self.write_and_publish_repository(export_client=False)

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
    TUFTestFixtureSimple()
    TUFTestFixtureDelegated()
    TUFTestFixtureNestedDelegated()
    TUFTestFixtureUnsupportedDelegation()
    TUFTestFixtureNestedDelegatedErrors()
    TUFTestFixtureAttackRollback()
    TUFTestFixtureThresholdTwo()
    TUFTestFixtureThresholdTwoAttack()


generate_fixtures()
