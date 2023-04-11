import string

from fixtures.builder import FixtureBuilder

def build():
    builder = FixtureBuilder('HashedBins')\
        .publish(with_client=True)

    for c in list(string.ascii_lowercase):
        builder.create_target(c + '.txt', signing_role=None)
