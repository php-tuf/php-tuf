<?php


namespace Tuf\Exception\PotentialAttackException;

use Tuf\Exception\TufException;

/**
 * Class PotentialAttackException
 *   Base class for all failures to verify trust in remote repository metadata or in a remote target.
 */
abstract class PotentialAttackException extends TufException
{
}
