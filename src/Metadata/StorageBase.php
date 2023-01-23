<?php

namespace Tuf\Metadata;

abstract class StorageBase implements StorageInterface
{
    abstract protected function read(string $name): ?string;

    abstract protected function write(string $name, string $data): void;

    public function getRoot(): RootMetadata
    {
        $data = $this->read(RootMetadata::TYPE);
        if ($data) {
            return RootMetadata::createFromJson($data)->trust();
        }
        throw new \LogicException("Could not load root metadata.");
    }

    public function setRoot(RootMetadata $metadata): void
    {
        $metadata->ensureIsTrusted();
        $this->write(RootMetadata::TYPE, $metadata->getSource());
    }

    public function getTimestamp(): ?TimestampMetadata
    {
        $data = $this->read(TimestampMetadata::TYPE);
        return $data ? TimestampMetadata::createFromJson($data)->trust() : null;
    }

    public function setTimestamp(TimestampMetadata $metadata): void
    {
        $metadata->ensureIsTrusted();
        $this->write(TimestampMetadata::TYPE, $metadata->getSource());
    }

    public function getSnapshot(): ?SnapshotMetadata
    {
        $data = $this->read(SnapshotMetadata::TYPE);
        return $data ? SnapshotMetadata::createFromJson($data)->trust() : null;
    }

    public function setSnapshot(SnapshotMetadata $metadata): void
    {
        $metadata->ensureIsTrusted();
        $this->write(SnapshotMetadata::TYPE, $metadata->getSource());
    }

    public function getTargets(string $role = 'targets'): ?TargetsMetadata
    {
        $data = $this->read($role);
        return $data ? TargetsMetadata::createFromJson($data, $role)->trust() : null;
    }

    public function setTargets(TargetsMetadata $metadata): void
    {
        $metadata->ensureIsTrusted();
        $this->write($metadata->getRole(), $metadata->getSource());
    }
}
