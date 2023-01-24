<?php

namespace Tuf\Metadata;

/**
 * Defines an interface for saving and loading trusted TUF metadata.
 */
interface StorageInterface
{
    /**
     * Loads trusted root metadata.
     *
     * @return \Tuf\Metadata\RootMetadata
     *   The trusted root metadata.
     *
     * @throws \LogicException
     *   Thrown if the root metadata cannot be loaded.
     */
    public function getRoot(): RootMetadata;

    /**
     * Persists the trusted root metadata.
     *
     * @param \Tuf\Metadata\RootMetadata $metadata
     *   The root metadata to persist. Must be trusted.
     */
    public function saveRoot(RootMetadata $metadata): void;

    /**
     * Loads trusted timestamp metadata.
     *
     * @return \Tuf\Metadata\TimestampMetadata|null
     *   The trusted timestamp metadata, or null if none is available.
     */
    public function getTimestamp(): ?TimestampMetadata;

    /**
     * Persists the trusted timestamp metadata.
     *
     * @param \Tuf\Metadata\TimestampMetadata $metadata
     *   The timestamp metadata to persist. Must be trusted.
     */
    public function saveTimestamp(TimestampMetadata $metadata): void;

    /**
     * Loads trusted snapshot metadata.
     *
     * @return \Tuf\Metadata\SnapshotMetadata|null
     *   The trusted snapshot metadata, or null if none is available.
     */
    public function getSnapshot(): ?SnapshotMetadata;

    /**
     * Persists the trusted snapshot metadata.
     *
     * @param \Tuf\Metadata\SnapshotMetadata $metadata
     *   The snapshot metadata to persist. Must be trusted.
     */
    public function saveSnapshot(SnapshotMetadata $metadata): void;

    /**
     * Loads trusted targets metadata for a specific role.
     *
     * @param string $role
     *   (optional) The role to load. Defaults to `targets`.
     *
     * @return \Tuf\Metadata\TargetsMetadata|null
     *   The trusted targets metadata, or null if none is available.
     */
    public function getTargets(string $role = 'targets'): ?TargetsMetadata;

    /**
     * Persists trusted targets metadata.
     *
     * @param \Tuf\Metadata\TargetsMetadata $metadata
     *   The targets metadata to persist. Can be for the root `targets` role
     *   or a delegated role, but must be trusted in any case.
     */
    public function saveTargets(TargetsMetadata $metadata): void;

    /**
     * Deletes stored metadata.
     *
     * @param string $name
     *   The name of the metadata to delete, without file extension.
     */
    public function delete(string $name): void;
}
