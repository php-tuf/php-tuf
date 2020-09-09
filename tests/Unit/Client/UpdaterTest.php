<?php

namespace Tuf\Tests\Unit\Client;

use PHPUnit\Framework\TestCase;
use Tuf\Client\Updater;
use Tuf\Metadata\MetadataBase;

use Tuf\Tests\TestHelpers\DurableStorage\MemoryStorageLoaderTrait;

/**
 * @covers \Tuf\Client\Updater
 */
class UpdaterTest extends TestCase
{
    use MemoryStorageLoaderTrait;

    protected function getSystemInTest() : Updater
    {
        $localRepo = $this->memoryStorageFromFixture('tufclient/tufrepo/metadata/current');
        return new Updater('repo', [], $localRepo);
    }

    /**
     * @covers ::checkRollbackAttack
     */
    public function testCheckRollbackAttackNoAttack()
    {
        // We test lack of an exception in the positive test case.
        $this->expectNotToPerformAssertions();

        $localMetadata = $this->getMockBuilder(MetadataBase::class)->disableOriginalConstructor()->getMock();
        $localMetadata->expects(self::any())->method('getType')->willReturn('any');
        $localMetadata->expects(self::any())->method('getVersion')->willReturn('1');
        $incomingMetadata = $this->getMockBuilder(MetadataBase::class)->disableOriginalConstructor()->getMock();
        $incomingMetadata->expects(self::any())->method('getType')->willReturn('any');
        $incomingMetadata->expects(self::any())->method('getVersion')->willReturn('2');
        $sut = $this->getSystemInTest();
        $method = new \ReflectionMethod(Updater::class, 'checkRollbackAttack');
        $method->setAccessible(true);
        $method->invoke($sut, $localMetadata, $incomingMetadata);

        // Incoming at same version as local.
        $incomingMetadata->expects(self::any())->method('getVersion')->willReturn('2');
        $method->invoke($sut, $localMetadata, $incomingMetadata);
    }

    /**
     * @covers ::checkRollbackAttack
     */
    public function testCheckRollbackAttackAttack()
    {
        $this->expectException('\Tuf\Exception\PotentialAttackException\RollbackAttackException');

        $sut = $this->getSystemInTest();
        $localMetadata = $this->getMockBuilder(MetadataBase::class)->disableOriginalConstructor()->getMock();
        $localMetadata->expects(self::any())->method('getType')->willReturn('any');
        $localMetadata->expects(self::any())->method('getVersion')->willReturn('2');
        $incomingMetadata = $this->getMockBuilder(MetadataBase::class)->disableOriginalConstructor()->getMock();
        $incomingMetadata->expects(self::any())->method('getType')->willReturn('any');
        $incomingMetadata->expects(self::any())->method('getVersion')->willReturn('1');
        $method = new \ReflectionMethod(Updater::class, 'checkRollbackAttack');
        $method->setAccessible(true);
        $method->invoke($sut, $localMetadata, $incomingMetadata);
    }

    /**
     * @covers ::checkFreezeAttack
     */
    public function testCheckFreezeAttackNoAttack()
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
        $method->invoke($sut, $signedMetadata, $now);

        // At expiration time.
        $signedMetadata->expects(self::any())->method('getExpires')->willReturn($nowString);
        $method->invoke($sut, $signedMetadata, $now);
    }

    /**
     * @covers ::checkFreezeAttack
     */
    public function testCheckFreezeAttackAttack()
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
        $method->invoke($sut, $signedMetadata, $now);
    }
}
