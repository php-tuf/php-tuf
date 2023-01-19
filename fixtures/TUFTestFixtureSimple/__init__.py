from fixtures.builder import ConsistencyVariantFixtureBuilder


def build():
    ConsistencyVariantFixtureBuilder('TUFTestFixtureSimple')\
        .create_target('testtarget.txt')\
        .publish(with_client=True)
