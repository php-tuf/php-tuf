from fixtures.builder import FixtureBuilder


def build():
    FixtureBuilder('TUFTestFixtureNestedTerminatingNonDelegatingDelegation')\
        .publish(with_client=True)\
        .create_target('targets.txt')\
        .delegate('a', ['*.txt'])\
        .create_target('a.txt', signing_role='a')\
        .delegate('b', ['*.txt'], parent='a', terminating=True) \
        .create_target('b.txt', signing_role='b') \
        .delegate('c', ['*.txt'], parent='a') \
        .create_target('c.txt', signing_role='c') \
        .delegate('d', ['*.txt']) \
        .create_target('d.txt', signing_role='d') \
        .publish()
