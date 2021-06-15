from fixtures.builder import FixtureBuilder


def build():
    fixture = FixtureBuilder('TUFTestFixtureNestedDelegatedErrors')\
        .publish(with_client=True)\
        .delegate('unclaimed', ['level_1_*.txt'])\
        .create_target('level_1_target.txt', signing_role='unclaimed')\
        .publish(with_client=True)
    # === Point of No Return ===
    # Past this point, we don't re-export the client. This supports testing the
    # client's own ability to pick up and trust new data from the repository.
    fixture.add_key('targets')\
        .add_key('snapshot')\
        .invalidate()\
        .publish()\
        .revoke_key('targets')\
        .revoke_key('snapshot')\
        .invalidate()\
        .publish()

    # Delegate from level_1_delegation to level_2
    fixture.delegate('level_2', ['level_1_2_*.txt'], parent='unclaimed')

    # Create a terminating delegation
    fixture.delegate('level_2_terminating', ['level_1_2_terminating_*.txt'], parent='unclaimed')

    fixture.publish()

    # Add a target that does not match the path for the delegation.
    fixture.create_target('level_a.txt', signing_role='unclaimed')
    # Add a target that matches the path parent delegation but not the current delegation.
    fixture.create_target('level_1_3_target.txt', signing_role='level_2')
    # Add a target that does not match the delegation's paths.
    fixture.create_target('level_2_unfindable.txt', signing_role='level_2_terminating')

    # Add a delegation after level_2_terminating which will not be evaluated.
    fixture.delegate('level_2_after_terminating', ['level_2_*.txt'], parent='unclaimed')\
        .create_target('level_2_after_terminating_unfindable.txt', signing_role='level_2_after_terminating')\
        .publish()
