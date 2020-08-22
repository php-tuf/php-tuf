<?php


namespace Tuf\Tests\Unit\Client;

use PHPUnit\Framework\TestCase;
use Tuf\Tests\PublicVisibility\Client\Updater;
use Tuf\Tests\TestHelpers\DurableStorage\MemoryStorageLoaderTrait;

class UpdaterTest extends TestCase
{
    use MemoryStorageLoaderTrait;

    protected function getSystemInTest() : Updater
    {
        $localRepo = $this->memoryStorageFromFixture('tufclient/tufrepo/metadata/current');
        return new Updater('repo', [], $localRepo);
    }

    public function testCheckRollbackAttack_noAttack()
    {
        // We test lack of an exception in the positive test case.
        $this->expectNotToPerformAssertions();

        $sut = $this->getSystemInTest();
        $localMetadata = [
            '_type' => 'any',
            'version' => 1,
        ];
        $incomingMetadata = [
            '_type' => 'any',
            'version' => 2,
        ];

        $sut->unitTest_checkRollbackAttack($localMetadata, $incomingMetadata);

        // Incoming at same version as local.
        $incomingMetadata['version'] = $localMetadata['version'];
        $sut->unitTest_checkRollbackAttack($localMetadata, $incomingMetadata);
    }

    public function testCheckRollbackAttack_attack()
    {
        $this->expectException('\Tuf\Exception\PotentialAttackException\RollbackAttackException');

        $sut = $this->getSystemInTest();
        $localMetadata = [
            '_type' => 'any',
            'version' => 2,
        ];
        $incomingMetadata = [
            '_type' => 'any',
            'version' => 1,
        ];

        $sut->unitTest_checkRollbackAttack($localMetadata, $incomingMetadata);
    }

    public function testCheckFreezeAttack_noAttack()
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

        $sut->unitTest_checkFreezeAttack($signedMetadata, $now);

        // At expiration time.
        $signedMetadata['expires'] = $nowString;
        $sut->unitTest_checkFreezeAttack($signedMetadata, $now);
    }

    public function testCheckFreezeAttack_attack()
    {
        $this->expectException('\Tuf\Exception\PotentialAttackException\FreezeAttackException');

        $sut = $this->getSystemInTest();
        $signedMetadata = [
            '_type' => 'any',
            'expires' => '1970-01-01T00:00:00Z',
        ];
        // 1 second later.
        $now = \DateTimeImmutable::createFromFormat("Y-m-d\TH:i:sT", '1970-01-01T00:00:01Z');

        $sut->unitTest_checkFreezeAttack($signedMetadata, $now);
    }
}
