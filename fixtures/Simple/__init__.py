from fixtures.builder import ConsistencyVariantFixtureBuilder


def build():
    ConsistencyVariantFixtureBuilder('Simple')\
        .create_target('testtarget.txt')\
        .publish(with_client=True)
