<?php

namespace Tuf\Tests\Client;

/**
 * A test updater that test the MAXIMUM_TARGET_ROLES enforcement.
 */
class LimitRolesTestUpdater extends TestUpdater
{
    const MAXIMUM_TARGET_ROLES = 3;
}