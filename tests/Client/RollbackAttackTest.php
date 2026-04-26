<?php

namespace Tuf\Tests\Client;

use Tuf\Exception\Attack\RollbackAttackException;
use Tuf\Tests\ClientTestBase;
use Tuf\Tests\FixtureBuilder\Fixture;
use Tuf\Tests\TestHelpers\DurableStorage\TestStorage;

/**
 * Tests that server-side rollback attacks are detected.
 */
class RollbackAttackTest extends ClientTestBase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Always throw an exception if writing to client storage.
        $this->clientStorage = new class () extends TestStorage {

            /**
             * {@inheritDoc}
             */
            public function write(string $name, string $data): void
            {
                throw new \LogicException("Unexpected attempt to change client storage.");
            }

            /**
             * {@inheritDoc}
             */
            public function delete(string $name): void
            {
                throw new \LogicException("Unexpected attempt to change client storage.");
            }

        };
    }

    /**
     * @testWith [true]
     *   [false]
     */
    public function testRollbackAttackDetection(bool $consistentSnapshot): void
    {
        $fixture = new Fixture();
        $fixture->root->consistentSnapshot = $consistentSnapshot;
        $fixture->createTarget('testtarget.txt');
        $fixture->publish(true);
        $backupDir = $fixture->serverDir . '_backup';
        $fixture->fileSystem->rename($fixture->serverDir, $backupDir);
        // Because the client will now have newer information than the server,
        // TUF will consider this a rollback attack.
        $fixture->createTarget('testtarget2.txt');
        $fixture->invalidate();
        $fixture->publish(true);
        $fixture->fileSystem->remove($fixture->serverDir);
        $fixture->fileSystem->rename($backupDir, $fixture->serverDir);

        $this->loadClientAndServerFilesFromFixture($fixture, [
            'root' => 2,
            'timestamp' => 2,
            'snapshot' => 2,
            'targets' => 2,
        ]);
        try {
            // § 5.4.3
            // § 5.4.4
            $this->getUpdater()->refresh();
            $this->fail('No exception thrown.');
        } catch (RollbackAttackException $exception) {
            $this->assertSame('Remote timestamp metadata version "$1" is less than previously seen timestamp version "$2"', $exception->getMessage());
            $this->assertMetadataVersions([
                'root' => 2,
                'timestamp' => 2,
                'snapshot' => 2,
                'targets' => 2,
            ], $this->clientStorage);
        }
    }
}
