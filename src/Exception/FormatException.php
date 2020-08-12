<?php

namespace Tuf\Exception;

/**
 *  Indicates an input was not in the required format to be interpreted.
 */
class FormatException extends TufException
{

    public function __construct($malformedValue, $message = "", \Throwable $previous = null)
    {
        if (empty($message)) {
            $message = 'Bad format';
        }
        $message = $message . sprintf(": %s", $malformedValue);
        parent::__construct($message, 0, $previous);
    }

}
