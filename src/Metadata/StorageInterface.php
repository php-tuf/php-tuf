<?php

namespace Tuf\Metadata;

interface StorageInterface
{
    public function getRoot(): RootMetadata;

    public function setRoot(RootMetadata $metadata): void;

    public function getTimestamp(): ?TimestampMetadata;

    public function setTimestamp(TimestampMetadata $metadata): void;

    public function getSnapshot(): ?SnapshotMetadata;

    public function setSnapshot(SnapshotMetadata $metadata): void;

    public function getTargets(string $role = 'targets'): ?TargetsMetadata;

    public function setTargets(TargetsMetadata $metadata): void;

    public function delete(string $name): void;
}
