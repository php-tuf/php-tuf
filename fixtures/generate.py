from tuf.repository_tool import *
import os
import shutil
from glob import glob
from pprint import pprint
from datetime import datetime, timedelta
import json
import subprocess


# This file largely derives from the TUF tutorial:
# https://github.com/theupdateframework/tuf/blob/develop/docs/TUTORIAL.md

def write_and_import_keypair(filename):
    pathpriv = 'tufkeystore/{}_key'.format(filename)
    pathpub = '{}.pub'.format(pathpriv)
    generate_and_write_ed25519_keypair(pathpriv, password='pw')
    public_key = import_ed25519_publickey_from_file(pathpub)
    private_key = import_ed25519_privatekey_from_file(pathpriv, password='pw')
    return (public_key, private_key)


def del_directory(directory):
    if os.path.isdir(directory): shutil.rmtree(directory + '/')


def create_directory(feature_set):
    # Clean up previously created repo
    if os.path.isdir(feature_set): shutil.rmtree(feature_set + '/')
    os.mkdir(feature_set)
    os.chdir(feature_set)


def backup_repo():
    max_backup = 0
    for dir in glob('backup_*'):
        backup = int(dir.replace('backup_', ''))
        if backup > max_backup:
            max_backup = backup
    max_backup += 1
    shutil.copytree('tufrepo/', 'backup_' + str(max_backup))


def remove_backups():
    for backup_dir in glob('*/backup_*'):
        shutil.rmtree(backup_dir)


def rm_files(pattern):
    for filename in glob(pattern):
        os.remove(filename)


def copy_files(pattern, dest):
    for filename in glob(pattern):
        shutil.copy(filename, dest)


def write_dirty_repo(repository, roles, create_client=False):
    repository.status()
    # Mark everything below the root as dirty.
    repository.mark_dirty(roles)
    repository.writeall(consistent_snapshot=True)
    # Publish the metadata
    del_directory('tufrepo/metadata')
    shutil.copytree('tufrepo/metadata.staged/', 'tufrepo/metadata/')
    # Always back up the current state of the repo. This allows making fixture sets for potential attacks. This backup
    # folders will be removed after creating fixtures.
    backup_repo()
    if create_client:
        # Generate client metadata
        del_directory('tufclient/tufrepo')
        create_tuf_client_directory("tufrepo/", "tufclient/tufrepo/")


def create_repo_fixtures(feature_set):
    create_directory(feature_set)
    # Create and Import Keypairs
    (public_root_key, private_root_key) = write_and_import_keypair('root')
    (public_targets_key, private_targets_key) = write_and_import_keypair('targets')
    (public_snapshots_key, private_snapshots_key) = write_and_import_keypair('snapshot')
    (public_timestamps_key, private_timestamps_key) = write_and_import_keypair('timestamp')
    # Bootstrap Repository
    repository = create_new_repository("tufrepo", feature_set)
    repository.root.add_verification_key(public_root_key)
    repository.root.load_signing_key(private_root_key)
    # Add additional roles
    repository.targets.add_verification_key(public_targets_key)
    repository.targets.load_signing_key(private_targets_key)
    repository.snapshot.add_verification_key(public_snapshots_key)
    repository.snapshot.load_signing_key(private_snapshots_key)
    repository.timestamp.add_verification_key(public_timestamps_key)
    repository.timestamp.load_signing_key(private_timestamps_key)
    write_dirty_repo(repository, ['root', 'snapshot', 'targets', 'timestamp'])

    # Write a test target
    with open('tufrepo/targets/testtarget.txt', 'w') as targetfile:
        targetfile.write("Test File")
    list_of_targets = ['testtarget.txt']
    repository.targets.add_targets(list_of_targets)
    # Mark everything below the root as dirty.
    write_dirty_repo(repository, ['snapshot', 'targets', 'timestamp'])

    if feature_set == 'simple':
        # Generate client metadata
        create_tuf_client_directory("tufrepo/", "tufclient/tufrepo/")
    # Delegate to an unclaimed target-signing key
    (public_unclaimed_key, private_unclaimed_key) = write_and_import_keypair('targets_delegated')
    repository.targets.delegate('unclaimed', [public_unclaimed_key], ['testunclaimed*.txt'])
    # Add a target signed by the delegated key
    with open('tufrepo/targets/testunclaimedtarget.txt', 'w') as targetfile:
        targetfile.write("Test Delegated File")
    repository.targets("unclaimed").add_target("testunclaimedtarget.txt")
    repository.targets("unclaimed").load_signing_key(private_unclaimed_key)
    write_dirty_repo(repository, ['snapshot', 'targets', 'timestamp', 'unclaimed'])

    if feature_set == 'simple':
        # Move back to original directory.
        os.chdir('..')
        return

    # Generate client metadata
    create_tuf_client_directory("tufrepo/", "tufclient/tufrepo/")

    # === Point of No Return ===
    # Past this point, we don't re-export the client. This supports testing the
    # client's own ability to pick up and trust new data from the repository.
    # Generate new keys for target and snapshot roles.
    (public_targets_key_2, private_targets_key_2) = write_and_import_keypair('targets2')
    (public_snapshots_key_2, private_snapshots_key_2) = write_and_import_keypair('snapshot2')
    # Add new verification keys.
    repository.targets.add_verification_key(public_targets_key_2)
    repository.targets.load_signing_key(private_targets_key_2)
    repository.snapshot.add_verification_key(public_snapshots_key_2)
    repository.snapshot.load_signing_key(private_snapshots_key_2)
    write_dirty_repo(repository, ['root', 'snapshot', 'targets', 'timestamp'])

    # Revoke the older keys.
    repository.targets.remove_verification_key(public_targets_key)
    repository.snapshot.remove_verification_key(public_snapshots_key)
    write_dirty_repo(repository, ['root', 'snapshot', 'targets', 'timestamp'])
    os.chdir('..')


# Create 2 fixture sets to test different scenarios.
create_repo_fixtures('delegated')
create_repo_fixtures('simple')

# Create a timestamp rollback.
del_directory('rollback_attack_timestamp')
shutil.copytree('delegated', 'rollback_attack_timestamp')
shutil.rmtree('rollback_attack_timestamp/tufrepo')
os.rename('rollback_attack_timestamp/backup_2', 'rollback_attack_timestamp/tufrepo')

del_directory('rollback_timestamp_snapshot_mismatch')
shutil.copytree('delegated', 'rollback_timestamp_snapshot_mismatch')
shutil.copy('rollback_timestamp_snapshot_mismatch/tufrepo/metadata/4.snapshot.json', 'rollback_timestamp_snapshot_mismatch/tufrepo/metadata/5.snapshot.json')


# rm_files('rollback_attack_snapshot/tufrepo/metadata/*snapshot.json')



# copy_files('rollback_attack_snapshot/backup_2/metadata/*snapshot.json', 'rollback_attack_snapshot/tufrepo/metadata')
remove_backups()


