<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

use Tuf\CanonicalJsonTrait;

/**
 * A payload is a builder for a server-side TUF metadata file.
 *
 * A payload represents a single role in the TUF repository. This could be one
 * of the four top-level roles (root, timestamp, snapshot, and targets), or it
 * could be a delegated targets role at any level of nesting.
 *
 * Every payload has relationships to other payloads. There are two kinds
 * of relationships:
 *
 * - A "parent" relationship is for a payload that enumerates other payloads.
 *   For example, a snapshot has a parent relationship to all targets roles,
 *   regardless of nesting level. The parent may needs to be updated when one
 *   of its children change something. (For example, the snapshot payload needs
 *   to be updated if any of the targets roles add or remove a target.)
 * - A "key ring" relationship is for payloads which sign other payloads. There
 *   are really two possible key rings in a TUF tree -- the root payload signs
 *   the top-level roles (including itself), so it is the key ring for those
 *   payloads. A targets role can sign its delegates, so it is the key ring for
 *   any roles it delegates to. Key rings only need to be updated if their
 *   children add or revoke any keys.
 */
abstract class Payload implements \Stringable
{
    use CanonicalJsonTrait;

    public int $threshold = 1;

    /**
     * The current version of this payload.
     *
     * Should not, generally, be changed by external code.
     *
     * @var int
     */
    public int $version = 1;

    /**
     * Whether this payload has changes that necessitate a version bump.
     *
     * @var bool
     */
    public bool $isDirty = true;

    protected array $revokedKeys = [];

    protected array $payloads = [];

    final public const FILE_EXTENSION = 'json';

    public function __construct(
        public readonly string $name,
        protected readonly ?Payload $keyRing,
        protected readonly ?Payload $parent,
        public \DateTimeImmutable $expires,
        protected array $signingKeys = [],
    ) {
        $keyRing?->watch($this);
        $parent?->watch($this);
    }

    final public function markAsDirty(): void
    {
        if ($this->isDirty === false) {
            $this->isDirty = true;
            $this->version++;
        }
    }

    /**
     * Adds an additional key which will sign this payload.
     */
    public function addKey(): void
    {
        $this->signingKeys[] = Key::fromStaticList();

        $this->markAsDirty();
        // Since we have added a key, our key ring (the role that signs us)
        // will need to be updated as well.
        $this->keyRing?->markAsDirty();
    }

    /**
     * Revokes one of the keys which will sign this payload.
     *
     * @param int $which
     *   The numeric index of the key to revoke. For the oldest key, pass 0. For
     *   the newest key, pass -1.
     */
    public function revokeKey(int $which): void
    {
        // The metadata needs to be signed with ALL of the keys (including
        // revoked ones), but we still need to keep track of the revoked keys
        // because the key ring needs to be made aware that the revoked key is
        // no longer acceptable for this metadata.
        array_push($this->revokedKeys, ...array_splice($this->signingKeys, $which, 1));

        $this->markAsDirty();
        // Since we have revoked a key, our key ring (the role that signs us)
        // will need to be updated as well.
        $this->keyRing?->markAsDirty();
    }

    /**
     * Establishes a relationship between this payload and another one.
     *
     * This would be used if, for example, a targets role was delegating to
     * another targets role. The delegated role is effectively a "child" of the
     * parent, so the parent needs to be able to loop through its children and
     * examine them.
     *
     * @param \Tuf\Tests\FixtureBuilder\Payload $payload
     *   Another payload.
     */
    protected function watch(Payload $payload): void
    {
        $this->payloads[$payload->name] = $payload;
        $this->markAsDirty();
    }

    /**
     * Generates the complete, signed string representation of this metadata.
     *
     * The value returned from this method can be written directly to a file,
     * and read by a TUF client.
     *
     * @return string
     */
    final public function __toString(): string
    {
        $payload = $this->toArray();

        $data = [
            'signatures' => array_map(fn(Key $key) => $key->sign($payload), [
                ...$this->signingKeys,
                ...$this->revokedKeys,
            ]),
            'signed' => $payload,
        ];
        return self::encodeJson($data, JSON_PRETTY_PRINT);
    }

    /**
     * Generates the `signed` part of the payload (everything but the
     * actual signatures).
     *
     * This gets signed, sorted, and converted to a canonical(-ish) JSON form by
     * ::__toString().
     *
     * @return array
     */
    protected function toArray(): array
    {
        return [
            'expires' => $this->expires->format('Y-m-d\TH:i:sp'),
            'spec_version' => '1.0.0',
            'version' => $this->version,
            '_type' => $this->name,
        ];
    }
}
