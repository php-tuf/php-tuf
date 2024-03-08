from fixtures.builder import ConsistencyVariantFixtureBuilder


def build():
    fixture = ConsistencyVariantFixtureBuilder('NestedDelegated')\
        .create_target('testtarget.txt')\
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
    fixture.delegate('level_2', ['level_1_2_*.txt'], parent='unclaimed')\
        .create_target('level_1_2_target.txt', signing_role='level_2')

    # Create a terminating delegation
    fixture.delegate('level_2_terminating', ['level_1_2_terminating_*.txt'], parent='unclaimed', terminating=True)\
        .create_target('level_1_2_terminating_findable.txt', signing_role='level_2_terminating')

    # Create a delegation under non-terminating 'level_2' delegation.
    fixture.delegate('level_3', ['level_1_2_3_*.txt'], parent='level_2')\
        .create_target('level_1_2_3_below_non_terminating_target.txt', signing_role='level_3')

    # Add a delegation below the 'level_2_terminating' role.
    # Delegations from a terminating role are evaluated but delegations after a terminating delegation
    # are not.
    # See NestedDelegatedErrors
    fixture.delegate('level_3_below_terminated', ['level_1_2_terminating_3_*.txt'], parent='level_2_terminating')\
        .create_target('level_1_2_terminating_3_target.txt', signing_role='level_3_below_terminated')

    # Add a delegation after level_2_terminating, but the path does not match level_2_terminating,
    # which WILL be evaluated.
    fixture.delegate('level_2_after_terminating_not_match_terminating_path', ['level_1_2a_terminating_plus_1_more_*.txt'], parent='unclaimed')\
        .create_target('level_1_2a_terminating_plus_1_more_findable.txt', signing_role='level_2_after_terminating_not_match_terminating_path')

    fixture.publish()
