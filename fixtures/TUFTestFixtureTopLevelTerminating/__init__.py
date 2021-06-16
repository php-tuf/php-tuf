from fixtures.builder import FixtureBuilder


def build():
    FixtureBuilder('TUFTestFixtureTopLevelTerminating')\
        .publish(with_client=True)\
        .create_target('targets.txt')\
        .delegate('a', ['*.txt'], terminating=True)\
        .create_target('a.txt', signing_role='a')\
        .delegate('b', ['*.txt'])\
        .create_target('b.txt', signing_role='b')\
        .publish()
