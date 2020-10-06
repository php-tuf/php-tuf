<?php

namespace Tuf\Tests\Unit\Client;

use PHPUnit\Framework\TestCase;
use Tuf\Client\RemoteRepoFileFetcherInterface;
use Tuf\Client\Updater;
use Tuf\Tests\TestHelpers\DurableStorage\MemoryStorageLoaderTrait;

/**
 * @covers \Tuf\Client\Updater
 */
class UpdaterTest extends TestCase
{

    /**
     * Creates a test Updater using memory storage of client fixture data.
     *
     * @return \Tuf\Client\Updater
     *     The test Updater from the 'current' test fixture data.
     */
    protected function getSystemInTest() : Updater
    {
        $localRepo = $this->getMockBuilder(\ArrayAccess::class)->getMock();
        $repoFetcher = $this->getMockBuilder(RemoteRepoFileFetcherInterface::class)->getMock();
        return new Updater($repoFetcher, [], $localRepo);
    }

    /**
     * Tests that no rollback attack is flagged when one is not performed.
     *
     * @covers ::checkRollbackAttack
     *
     * @return void
     */
    public function testCheckRollbackAttackNoAttack() : void
    {
        // We test lack of an exception in the positive test case.
        $this->expectNotToPerformAssertions();

        $sut = $this->getSystemInTest();

        // The incoming version is newer than the local version, so no
        // rollback attack is present.
        $localMetadata = [
            '_type' => 'any',
            'version' => 1,
        ];
        $incomingMetadata = [
            '_type' => 'any',
            'version' => 2,
        ];
        $method = new \ReflectionMethod(Updater::class, 'checkRollbackAttack');
        $method->setAccessible(true);
        $method->invoke($sut, $localMetadata, $incomingMetadata);

        // Incoming at same version as local.
        $incomingMetadata['version'] = $localMetadata['version'];
        $method->invoke($sut, $localMetadata, $incomingMetadata);
    }

    /**
     * Tests that the correct exception is thrown in case of a rollback attack.
     *
     * @covers ::checkRollbackAttack
     *
     * @return void
     */
    public function testCheckRollbackAttackAttack() : void
    {
        $this->expectException('\Tuf\Exception\PotentialAttackException\RollbackAttackException');

        $sut = $this->getSystemInTest();

        // The incoming version is lower than the local version, so this should
        // be identified as a rollback attack.
        $localMetadata = [
            '_type' => 'any',
            'version' => 2,
        ];
        $incomingMetadata = [
            '_type' => 'any',
            'version' => 1,
        ];
        $method = new \ReflectionMethod(Updater::class, 'checkRollbackAttack');
        $method->setAccessible(true);
        $method->invoke($sut, $localMetadata, $incomingMetadata);
    }

    /**
     * Tests that no freeze attack is flagged when the data has not expired.
     *
     * @covers ::checkFreezeAttack
     *
     * @return void
     */
    public function testCheckFreezeAttackNoAttack() : void
    {
        // We test lack of an exception in the positive test case.
        $this->expectNotToPerformAssertions();

        $sut = $this->getSystemInTest();
        $signedMetadata = [
            '_type' => 'any',
            'expires' => '1970-01-01T00:00:01Z',
        ];
        // 1 second earlier.
        $nowString = '1970-01-01T00:00:00Z';
        $now = \DateTimeImmutable::createFromFormat("Y-m-d\TH:i:sT", $nowString);

        $method = new \ReflectionMethod(Updater::class, 'checkFreezeAttack');
        $method->setAccessible(true);

        // The update's expiration is later than now, so no freeze attack
        // exception should be thrown.
        $method->invoke($sut, $signedMetadata, $now);

        // No exception should be thrown exactly at expiration time.
        $signedMetadata['expires'] = $nowString;
        $method->invoke($sut, $signedMetadata, $now);
    }

    /**
     * Tests that the correct exception is thrown when the update is expired.
     *
     * @covers ::checkFreezeAttack
     *
     * @return void
     */
    public function testCheckFreezeAttackAttack() : void
    {
        $this->expectException('\Tuf\Exception\PotentialAttackException\FreezeAttackException');

        $sut = $this->getSystemInTest();
        $signedMetadata = [
            '_type' => 'any',
            'expires' => '1970-01-01T00:00:00Z',
        ];
        // 1 second later.
        $now = \DateTimeImmutable::createFromFormat("Y-m-d\TH:i:sT", '1970-01-01T00:00:01Z');

        $method = new \ReflectionMethod(Updater::class, 'checkFreezeAttack');
        $method->setAccessible(true);

        // The update has already expired, so a freeze attack exception should
        // be thrown.
        $method->invoke($sut, $signedMetadata, $now);
    }
}
