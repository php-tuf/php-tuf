<?php

namespace Tuf\Metadata;

/**
 * Defines an interface for metadata class that contain other metadata file info.
 */
interface MetaFileInfoInterface
{
    /**
     * Gets file information value under the 'meta' key.
     *
     * @param string $key
     *   The array key under 'meta'.
     *
     * @return mixed[]|null
     *   The file information if available or null if not set.
     */
    public function getFileMetaInfo(string $key);

}