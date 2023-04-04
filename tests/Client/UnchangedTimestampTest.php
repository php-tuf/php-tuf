<?php

namespace Tuf\Tests\Client;

use Tuf\Tests\ClientTestBase;

/**
 * Tests that the update is short-circuited if timestamp metadata is unchanged.
 */
class UnchangedTimestampTest extends ClientTestBase
{
    /**
     * @testWith ["consistent"]
     *   ["inconsistent"]
     */
    public function testUpdateShortCircuitsIfTimestampUnchanged(string $fixtureVariant): void
    {
        $this->loadClientAndServerFilesFromFixture("Simple/$fixtureVariant");

        // The removal of these files will cause an exception if the update
        // doesn't stop after downloading the unchanged timestamp metadata.
        unset($this->serverFiles['snapshot.json']);
        unset($this->serverFiles['targets.json']);
        $this->getUpdater()->refresh();
    }
}
