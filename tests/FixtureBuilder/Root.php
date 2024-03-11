<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

/**
 * A class that can be used to create TUF root metadata.
 */
final class Root extends Payload
{
    public ?bool $consistentSnapshot = null;

    public function __construct(mixed ...$arguments)
    {
        // The root role signs itself, so it has no key ring, and it has no
        // parent because no other role needs to be changed if this one does.
        parent::__construct('root', null, null, ...$arguments);
    }

    /**
     * {@inheritDoc}
     */
    protected function toArray(): array
    {
        $data = parent::toArray();

        /** @var Payload $role */
        // Loop through every role that we're watching (which should just be
        // the four top-level ones).
        foreach ([$this, ...$this->payloads] as $role) {
            $data['roles'][$role->name]['threshold'] = $role->threshold;

            foreach ($role->signingKeys as $key) {
                $id = $key->id();
                $data['keys'][$id] = $key->toArray();
                $data['roles'][$role->name]['keyids'][] = $id;
            }
        }

        if (is_bool($this->consistentSnapshot)) {
            $data['consistent_snapshot'] = $this->consistentSnapshot;
        }

        return $data;
    }
}
