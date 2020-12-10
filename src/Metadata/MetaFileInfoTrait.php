<?php

namespace Tuf\Metadata;

/**
 * A trait that implements MetaFileInterface.
 */
trait MetaFileInfoTrait
{

    /**
     * Gets file information value under the 'meta' key.
     *
     * @param string $key
     *   The array key under 'meta'.
     *
     * @return \ArrayObject|null
     *   The file information if available or null if not set.
     */
    public function getFileMetaInfo(string $key):?\ArrayObject
    {
        $signed = $this->getSigned();
        return $signed['meta'][$key] ?? null;
    }
}
