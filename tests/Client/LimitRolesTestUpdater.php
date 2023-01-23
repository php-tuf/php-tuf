<?php

namespace Tuf\Tests\Client;

use Tuf\Client\Updater;

/**
 * A test updater that test the MAXIMUM_TARGET_ROLES enforcement.
 */
class LimitRolesTestUpdater extends Updater
{
    const MAXIMUM_TARGET_ROLES = 3;
}
