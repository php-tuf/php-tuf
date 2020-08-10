<?php


namespace Tuf\Exception;

use Throwable;

/**
 * @todo Remove this class if not used.
 */
class NotFoundException extends TufException
{
    /**
     * @var string $key
     *   The unique identifier (id, file path, etc.) for the item that was not found.
     */
    public $key;

    /**
     * @var string $itemType
     *   The type of item (signing key, file, etc.) that was not found.
     */
    public $itemType;

    public function __construct($key = "", $itemType = "item", Throwable $previous = null)
    {
        $message = "$itemType not found";
        if ($key != "") {
            $message = "$itemType not found: $key";
        }
        $this->key = $key;
        $this->itemType = $itemType;
        parent::__construct($message, 0, $previous);
    }
}
