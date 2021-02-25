<?php

namespace Tuf\Exception\PotentialAttackException;

use Tuf\Exception\TufException;

/**
 * Indicates an invalid hash was computed for a downloaded target.
 */
class InvalidHashException extends TufException
{
}
