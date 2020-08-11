<?php


namespace Tuf\Tests;

use PHPUnit\Framework\TestCase;
use Tuf\KeyDB;

class KeyDBTest extends TestCase
{
  /**
   * @param $metadata
   * @dataProvider computeKeyIdProvider
   */
    public function testComputeKeyId($keyMeta, $want)
    {
        $actual = KeyDB::computeKeyIds($keyMeta);
        $this->assertEquals($want, $actual);
    }

    public function computeKeyIdProvider()
    {
        return [
            "case 1" => [
                0 => [
                    "keyid_hash_algorithms" => ['sha256', 'sha512'],
                    "keytype" => "ed25519",
                    "keyval" => ["public" => "edcd0a32a07dce33f7c7873aaffbff36d20ea30787574ead335eefd337e4dacd"],
                    "scheme" => "ed25519"
                ],
                1 => [
                    "59a4df8af818e9ed7abe0764c0b47b4240952aa0d179b5b78346c470ac30278d",
                    "594e8b4bdafc33fd87e9d03a95be13a6dc93a836086614fd421116d829af68d8f0110ae93e3dde9a246897fd85171455e"
                    . "a53191bb96cf9e589ba047d057dbd66",
                ],
            ],
        ];
    }
}
