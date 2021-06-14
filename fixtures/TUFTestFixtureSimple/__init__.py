from fixtures.builder import FixtureBuilder


def build():
    FixtureBuilder('TUFTestFixtureSimple')\
        .create_target('testtarget.txt')\
        .publish(with_client=True)
