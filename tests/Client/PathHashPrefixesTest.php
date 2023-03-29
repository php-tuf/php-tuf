<?php

namespace Tuf\Tests\Client;

use Tuf\Exception\MetadataException;
use Tuf\Tests\ClientTestBase;

/**
 * Tests that path_hash_prefixes are not supported in delegated roles.
 */
class PathHashPrefixesTest extends ClientTestBase
{
    public function provider(): array
    {
        return [
            'consistent' => [
                'consistent',
                [
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'unsupported_target' => null,
                ],
            ],
            'inconsistent' => [
                'inconsistent',
                [
                    'root' => 1,
                    'timestamp' => 2,
                    'snapshot' => 2,
                    'unsupported_target' => null,
                ],
            ],
        ];
    }

    /**
     * @dataProvider provider
     */
    public function testUnsupportedRepo(string $fixtureVariant, array $expectedUpdatedVersion): void
    {
        // We cannot assert the starting version of 'targets' because it has
        // an unsupported field and would throw an exception when validating.
        unset($expectedUpdatedVersion['targets']);

        $this->loadClientAndServerFilesFromFixture("UnsupportedDelegation/$fixtureVariant");
        $startingTargets = $this->clientStorage->read('targets');
        try {
            $this->getUpdater()->refresh();
            $this->fail('No exception thrown.');
        } catch (MetadataException $exception) {
            $expectedMessage = preg_quote("Array[signed][delegations][roles][0][path_hash_prefixes]:", '/');
            $expectedMessage .= ".*This field is not supported.";
            self::assertSame(1, preg_match("/$expectedMessage/s", $exception->getMessage()));
            // Assert that the root, timestamp and snapshot metadata files were updated
            // and that the unsupported_target metadata file was not downloaded.
            $this->assertMetadataVersions($expectedUpdatedVersion, $this->clientStorage);
            // Ensure that local version of targets has not changed because the
            // server version is invalid.
            self::assertSame($this->clientStorage->read('targets'), $startingTargets);
        }
    }
}
