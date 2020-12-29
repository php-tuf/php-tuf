<?php

namespace Tuf\Metadata;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Type;

class RootMetadata extends MetadataBase
{

    /**
     * {@inheritdoc}
     */
    protected const TYPE = 'root';

    /**
     * {@inheritdoc}
     */
    protected static function getSignedCollectionOptions(): array
    {
        $options = parent::getSignedCollectionOptions();
        $options['fields']['keys'] = new Required([
            new Type('\ArrayObject'),
            new Count(['min' => 1]),
            new All([
                static::getKeyConstraints(),
            ]),
        ]);
        $roleConstraints = new Collection(
            self::getKeyidsConstraints() +
            static::getThresholdConstraints()
        );
        $options['fields']['roles'] = new Collection([
            'targets' => new Required($roleConstraints),
            'timestamp' => new Required($roleConstraints),
            'snapshot' => new Required($roleConstraints),
            'root' => new Required($roleConstraints),
            'mirror' => new Optional($roleConstraints),
        ]);
        $options['fields']['consistent_snapshot'] = new Required([
            new Type('boolean'),
        ]);
        return $options;
    }

    /**
     * Gets the roles from the metadata.
     *
     * @param boolean $allowUntrustedAccess
     *   Whether this method should access even if the metadata is not trusted.
     *
     * @return \ArrayObject
     *   An ArrayObject where the keys are role names and the values arrays with the
     *   following keys:
     *   - keyids (string[]): The key ids.
     *   - threshold (int): Determines how many how may keys are need from
     *     this role for signing.
     */
    public function getRoles(bool $allowUntrustedAccess = false):\ArrayObject
    {
        $this->ensureIsTrusted($allowUntrustedAccess);
        return $this->getSigned()['roles'];
    }

    /**
     * Gets the keys for the root metadata.
     *
     * @param boolean $allowUntrustedAccess
     *   Whether this method should access even if the metadata is not trusted.
     *
     * @return \ArrayObject
     *   An ArrayObject of keys information where the array keys are the key ids and
     *   the values are arrays with the following values:
     *   - keyid_hash_algorithms (string[]): The key id algorithms used.
     *   - keytype (string): The key type.
     *   - keyval (string[]): A single item array where key value is 'public'
     *     and the value is the public key.
     *   - scheme (string): The key scheme.
     */
    public function getKeys(bool $allowUntrustedAccess = false):\ArrayObject
    {
        $this->ensureIsTrusted($allowUntrustedAccess);
        return $this->getSigned()['keys'];
    }

    /**
     * Determines whether consistent snapshots are supported.
     *
     * @return boolean
     *   Whether consistent snapshots are supported.
     */
    public function supportsConsistentSnapshots() : bool
    {
        $this->ensureIsTrusted();
        return $this->getSigned()['consistent_snapshot'];
    }
}
