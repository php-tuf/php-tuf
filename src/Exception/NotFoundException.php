<?php

namespace Tuf\Exception;

/**
 * Indicates that an item was not found in the repository data.
 *
 * @todo Remove this class if not used.
 */
class NotFoundException extends TufException
{

    public function __construct($key = "", $itemType = "item", \Throwable $previous = null)
    {
        $message = "$itemType not found";
        if ($key != "") {
            $message = "$itemType not found: $key";
        }
        parent::__construct($message, 0, $previous);
    }
}
