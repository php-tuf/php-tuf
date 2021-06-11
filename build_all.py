import fixtures.TUFTestFixtureSimple


from unittest import mock

@mock.patch('time.time', mock.MagicMock(return_value=1577836800))
def build_all_new():
    fixtures.TUFTestFixtureSimple.build()

build_all_new()

