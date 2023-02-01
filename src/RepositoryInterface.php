<?php

namespace Tuf;

use GuzzleHttp\Promise\PromiseInterface;

/**
 * Defines an interface for retrieving untrusted metadata and targets.
 */
interface RepositoryInterface
{
    /**
     * Returns untrusted root metadata.
     *
     * @param int $version
     *   The version of the root metadata to get.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface<\Tuf\Metadata\RootMetadata>
     *   A promise wrapping an untrusted instance of \Tuf\Metadata\RootMetadata.
     *   If the requested root metadata version does not exist, the promise
     *   should be fulfilled with null.
     */
    public function getRoot(int $version): PromiseInterface;

    /**
     * Returns untrusted timestamp metadata.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface<\Tuf\Metadata\TimestampMetadata>
     *   A promise wrapping an untrusted instance of
     *   \Tuf\Metadata\TimestampMetadata.
     */
    public function getTimestamp(): PromiseInterface;

    /**
     * Returns untrusted snapshot metadata.
     *
     * @param int|null $version
     *   The version of the snapshot metadata to get, or null if consistent
     *   snapshots are not used.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface<\Tuf\Metadata\SnapshotMetadata>
     *   A promise wrapping an untrusted instance of
     *   \Tuf\Metadata\SnapshotMetadata.
     */
    public function getSnapshot(?int $version): PromiseInterface;

    /**
     * Returns untrusted targets metadata.
     *
     * @param int|null $version
     *   The version of the targets metadata to get, or null if consistent
     *   snapshots are not used.
     * @param string $role
     *   (optional) The role to fetch metadata for. Defaults to `targets`, but
     *   may be the name of a delegated role.
     *
     * @return \GuzzleHttp\Promise\PromiseInterface<\Tuf\Metadata\TargetsMetadata>
     *   A promise wrapping an untrusted instance of
     *   \Tuf\Metadata\TargetsMetadata.
     */
    public function getTargets(?int $version, string $role = 'targets'): PromiseInterface;
}
