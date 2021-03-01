<?php

namespace Tuf\Tests\Unit\Client;

use GuzzleHttp\Promise\FulfilledPromise;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophet;
use Tuf\Client\RepoFileFetcherInterface;
use Tuf\Client\Updater;
use Tuf\Exception\PotentialAttackException\InvalidHashException;
use Tuf\Metadata\MetadataBase;

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
        $repoFetcher = $this->getMockBuilder(RepoFileFetcherInterface::class)->getMock();
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

        // The incoming version is newer than the local version, so no
        // rollback attack is present.
        $localMetadata = $this->getMockBuilder(MetadataBase::class)->disableOriginalConstructor()->getMock();
        $localMetadata->expects(self::any())->method('getType')->willReturn('any');
        $localMetadata->expects(self::any())->method('getVersion')->willReturn(1);
        $incomingMetadata = $this->getMockBuilder(MetadataBase::class)->disableOriginalConstructor()->getMock();
        $incomingMetadata->expects(self::any())->method('getType')->willReturn('any');
        $incomingMetadata->expects(self::any())->method('getVersion')->willReturn(2);
        $sut = $this->getSystemInTest();
        $method = new \ReflectionMethod(Updater::class, 'checkRollbackAttack');
        $method->setAccessible(true);
        $method->invoke($sut, $localMetadata, $incomingMetadata);

        // Incoming at same version as local.
        $incomingMetadata->expects(self::any())->method('getVersion')->willReturn(2);
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
        $this->expectExceptionMessage('Remote any metadata version "$1" is less than previously seen any version "$2"');

        $sut = $this->getSystemInTest();

        // The incoming version is lower than the local version, so this should
        // be identified as a rollback attack.
        $localMetadata = $this->getMockBuilder(MetadataBase::class)->disableOriginalConstructor()->getMock();
        $localMetadata->expects(self::any())->method('getType')->willReturn('any');
        $localMetadata->expects(self::any())->method('getVersion')->willReturn(2);
        $incomingMetadata = $this->getMockBuilder(MetadataBase::class)->disableOriginalConstructor()->getMock();
        $incomingMetadata->expects(self::any())->method('getType')->willReturn('any');
        $incomingMetadata->expects(self::any())->method('getVersion')->willReturn(1);
        $method = new \ReflectionMethod(Updater::class, 'checkRollbackAttack');
        $method->setAccessible(true);
        $method->invoke($sut, $localMetadata, $incomingMetadata);
    }

    /**
     * Tests that the correct exception is thrown in case of a rollback attack
     * where the incoming metadata does not match the expected version.
     *
     * @covers ::checkRollbackAttack
     *
     * @return void
     */
    public function testCheckRollbackAttackAttackExpectedVersion() : void
    {
        $this->expectException('\Tuf\Exception\PotentialAttackException\RollbackAttackException');
        $this->expectExceptionMessage('Remote any metadata version "$2" does not the expected version "$3"');

        $sut = $this->getSystemInTest();

        // The incoming version is lower than the local version, so this should
        // be identified as a rollback attack.
        $localMetadata = $this->getMockBuilder(MetadataBase::class)->disableOriginalConstructor()->getMock();
        $localMetadata->expects(self::any())->method('getType')->willReturn('any');
        $incomingMetadata = $this->getMockBuilder(MetadataBase::class)->disableOriginalConstructor()->getMock();
        $incomingMetadata->expects(self::any())->method('getType')->willReturn('any');
        $incomingMetadata->expects(self::any())->method('getVersion')->willReturn(2);
        $method = new \ReflectionMethod(Updater::class, 'checkRollbackAttack');
        $method->setAccessible(true);
        $method->invoke($sut, $localMetadata, $incomingMetadata, 3);
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
        $signedMetadata = $this->getMockBuilder(MetadataBase::class)->disableOriginalConstructor()->getMock();
        $signedMetadata->expects(self::any())->method('getType')->willReturn('any');
        $signedMetadata->expects(self::any())->method('getExpires')->willReturn('1970-01-01T00:00:01Z');
        $nowString = '1970-01-01T00:00:00Z';
        $now = \DateTimeImmutable::createFromFormat("Y-m-d\TH:i:sT", $nowString);

        $method = new \ReflectionMethod(Updater::class, 'checkFreezeAttack');
        $method->setAccessible(true);

        // The update's expiration is later than now, so no freeze attack
        // exception should be thrown.
        $method->invoke($sut, $signedMetadata, $now);

        // No exception should be thrown exactly at expiration time.
        $signedMetadata->expects(self::any())->method('getExpires')->willReturn($nowString);
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
        $signedMetadata = $this->getMockBuilder(MetadataBase::class)->disableOriginalConstructor()->getMock();
        $signedMetadata->expects(self::any())->method('getType')->willReturn('any');
        $signedMetadata->expects(self::any())->method('getExpires')->willReturn('1970-01-01T00:00:00Z');
        // 1 second later.
        $now = \DateTimeImmutable::createFromFormat("Y-m-d\TH:i:sT", '1970-01-01T00:00:01Z');

        $method = new \ReflectionMethod(Updater::class, 'checkFreezeAttack');
        $method->setAccessible(true);

        // The update has already expired, so a freeze attack exception should
        // be thrown.
        $method->invoke($sut, $signedMetadata, $now);
    }

    /**
     * Tests that TUF will transparently verify downloaded target hashes.
     *
     * @covers ::download
     *
     * @return void
     */
    public function testVerifiedDownload(): void
    {
        $prophet = new Prophet();
        $fetcher = $prophet->prophesize(RepoFileFetcherInterface::class);
        $storage = new \ArrayObject([
            'targets.json' => file_get_contents(__DIR__ . '/../../../fixtures/TUFTestFixtureSimple/tufrepo/metadata/2.targets.json'),
        ]);
        $updater = new Updater($fetcher->reveal(), [], $storage);
        $promise = new FulfilledPromise('testtarget.txt');
        $fetcher->fetchFile('testtarget.txt', 14)->willReturn($promise);
        $updater->download('testtarget.txt')->wait();

        $promise = new FulfilledPromise('invalid data');
        $fetcher->fetchFile('testtarget.txt', 14)->willReturn($promise);
        $this->expectException(InvalidHashException::class);
        $this->expectExceptionMessage("Invalid sha256 hash for testtarget.txt");
        $updater->download('testtarget.txt')->wait();
    }
}
