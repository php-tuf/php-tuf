from fixtures.builder import FixtureBuilder

import os
import shutil


def build():
    variants = {
        'consistent': True,
        'inconsistent': False
    }
    for suffix, consistent in variants.items():
        name = os.path.join('TUFTestFixtureAttackRollback', suffix)
        fixture = FixtureBuilder(name)\
            .create_target('testtarget.txt')\
            .publish(with_client=True, consistent=consistent)

        server_dir = fixture._server_dir
        backup_dir = server_dir + '_backup'
        shutil.copytree(server_dir, backup_dir, dirs_exist_ok=True)

        fixture.create_target('testtarget2.txt')\
            .publish(with_client=True)
        shutil.rmtree(server_dir + '/')

        # Reset the client to previous state to simulate a rollback attack.
        shutil.copytree(backup_dir, server_dir, dirs_exist_ok=True)
        shutil.rmtree(backup_dir + '/')
