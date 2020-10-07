<?php

namespace Tuf\Tests\Client;

use PHPUnit\Framework\TestCase;
use Tuf\Client\Updater;
use Tuf\Exception\PotentialAttackException\SignatureThresholdExpception;
use Tuf\Metadata\RootMetadata;
use Tuf\Tests\TestHelpers\DurableStorage\MemoryStorageLoaderTrait;

class UpdaterTest extends TestCase
{
    use MemoryStorageLoaderTrait;

    /**
     * The local repo.
     *
     * @var \Tuf\Tests\TestHelpers\DurableStorage\MemoryStorage
     */
    protected $localRepo;

    /**
     * @var \Tuf\Tests\Client\TestRepo
     */
    protected $testRepo;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        // Use the memory storage used so tests can write without permanent
        // side-effects.
        $this->localRepo = $this->memoryStorageFromFixture('tufclient/tufrepo/metadata/current');

        $this->testRepo = new TestRepo();
    }

    /**
     * Returns a memory-based updater populated with the test fixtures.
     *
     * @return Updater
     *     The test updater, which uses the 'current' test fixtures in the
     *     tufclient/tufrepo/metadata/current/ directory and a localhost HTTP
     *     mirror.
     */
    protected function getSystemInTest() : Updater
    {
        $mirrors = [
            'mirror1' => [
                'url_prefix' => 'http://localhost:8001',
                'metadata_path' => 'metadata',
                'targets_path' => 'targets',
                'confined_target_dirs' => [],
            ],
        ];

        // Remove all '*.[TYPE].json' because they are needed for the tests.
        $fixtureFiles = scandir($this->getFixturesRealPath('tufclient/tufrepo/metadata/current'));
        $this->assertNotEmpty($fixtureFiles);
        foreach ($fixtureFiles as $fileName) {
            if (preg_match('/.*\..*\.json/', $fileName)) {
                unset($this->localRepo[$fileName]);
            }
        }
        $updater = new Updater($this->testRepo, $mirrors, $this->localRepo);
        return $updater;
    }

    /**
     * Tests refreshing the repository.
     *
     * @return void
     */
    public function testRefreshRepository() : void
    {
        $this->assertSame(3, RootMetadata::createFromJson($this->localRepo['root.json'])->getVersion());
        $updater = $this->getSystemInTest();
        $this->assertTrue($updater->refresh());
        // Confirm the root was updated to version 5 which is the highest
        // version in the test fixtures.
        $this->assertSame(
            5,
            RootMetadata::createFromJson($this->localRepo['root.json'])->getVersion()
        );
    }

    /**
     * Tests that an exception is thrown when attempting to update to root fail
     * that does not have a valid signature.
     *
     * @return void
     */
    public function testUpdateRootSignatureError() : void
    {
        $this->assertSame(3, RootMetadata::createFromJson($this->localRepo['root.json'])->getVersion());
        // Set '5.root.json' to fail the signature check.
        $this->testRepo->setFilesToFailSignature(['5.root.json']);
        $updater = $this->getSystemInTest();
        try {
            $updater->refresh();
        } catch (SignatureThresholdExpception $exception) {
            // Confirm the root was updated to version 4 but not to 5 because
            // 5.root.json should throw an exception.
            $this->assertSame(
                4,
                RootMetadata::createFromJson($this->localRepo['root.json'])->getVersion()
            );
            return;
        }
        $this->fail('No SignatureThresholdExpception thrown');
    }
}
