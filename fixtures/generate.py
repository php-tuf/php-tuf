from tuf.repository_tool import *
import os
import shutil

# This file largely derives from the TUF tutorial:
# https://github.com/theupdateframework/tuf/blob/develop/docs/TUTORIAL.md

def write_and_import_keypair(filename):
    pathpriv = 'tufkeystore/{}_key'.format(filename)
    pathpub = '{}.pub'.format(pathpriv)
    generate_and_write_ed25519_keypair(pathpriv, password='pw')
    public_key = import_ed25519_publickey_from_file(pathpub)
    private_key = import_ed25519_privatekey_from_file(pathpriv, password='pw')
    return (public_key, private_key)


def create_repo_fixtures(directory, complete_build):
    # Clean up
    if os.path.isdir(directory): shutil.rmtree(directory + '/')
    os.mkdir(directory)
    os.chdir(directory)
    # Create and Import Keypairs
    (public_root_key, private_root_key) = write_and_import_keypair('root')
    (public_targets_key, private_targets_key) = write_and_import_keypair('targets')
    (public_snapshots_key, private_snapshots_key) = write_and_import_keypair('snapshot')
    (public_timestamps_key, private_timestamps_key) = write_and_import_keypair('timestamp')
    # Bootstrap Repository
    repository = create_new_repository("tufrepo", directory)
    repository.root.add_verification_key(public_root_key)
    repository.root.load_signing_key(private_root_key)
    # Add additional roles
    repository.targets.add_verification_key(public_targets_key)
    repository.targets.load_signing_key(private_targets_key)
    repository.snapshot.add_verification_key(public_snapshots_key)
    repository.snapshot.load_signing_key(private_snapshots_key)
    repository.timestamp.add_verification_key(public_timestamps_key)
    repository.timestamp.load_signing_key(private_timestamps_key)
    repository.status()
    # Make it so (consistently)
    repository.mark_dirty(['root', 'snapshot', 'targets', 'timestamp'])
    repository.writeall(consistent_snapshot=True)
    # Write a test target
    with open('tufrepo/targets/testtarget.txt', 'w') as targetfile:
        targetfile.write("Test File")
    list_of_targets = ['testtarget.txt']
    repository.targets.add_targets(list_of_targets)
    # Mark everything below the root as dirty.
    repository.mark_dirty(['snapshot', 'targets', 'timestamp'])
    repository.writeall(consistent_snapshot=True)

    if complete_build == False:
        # Publish the metadata
        shutil.copytree('tufrepo/metadata.staged/', 'tufrepo/metadata/')
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
    repository.mark_dirty(['snapshot', 'targets', 'timestamp', 'unclaimed'])
    repository.writeall(consistent_snapshot=True)
    # Publish the metadata
    shutil.copytree('tufrepo/metadata.staged/', 'tufrepo/metadata/', dirs_exist_ok=True)

    if complete_build == False:
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
    repository.status()
    # Write the updated repository data.
    repository.mark_dirty(['root', 'snapshot', 'targets', 'timestamp'])
    repository.writeall(consistent_snapshot=True)
    shutil.copytree('tufrepo/metadata.staged/', 'tufrepo/metadata/', dirs_exist_ok=True)
    # Revoke the older keys.
    repository.targets.remove_verification_key(public_targets_key)
    repository.snapshot.remove_verification_key(public_snapshots_key)
    repository.status()
    # Write the updated repository data.
    repository.mark_dirty(['root', 'snapshot', 'targets', 'timestamp'])
    repository.writeall(consistent_snapshot=True)
    shutil.copytree('tufrepo/metadata.staged/', 'tufrepo/metadata/', dirs_exist_ok=True)
    os.chdir('..')


create_repo_fixtures('delegated', True)
create_repo_fixtures('simple', False)

