<?php

namespace Tuf\Metadata;

trait MetaFileInfoTrait
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
    public function getFileMetaInfo(string $key)
    {
        $signed = $this->getSigned();
        return $signed['meta'][$key] ?? null;
    }
}