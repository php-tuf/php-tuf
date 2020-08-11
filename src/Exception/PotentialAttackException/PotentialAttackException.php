<?php

namespace Tuf\Exception\PotentialAttackException;

use Tuf\Exception\TufException;

/**
 * Defines an exception base class for potential attacks.
 *
 * Use this class for all failures related to verifying trust in the remote
 * repository metadata or a remote target.
 */
abstract class PotentialAttackException extends TufException
{
}
