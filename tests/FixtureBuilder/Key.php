<?php

declare(strict_types=1);

namespace Tuf\Tests\FixtureBuilder;

use Tuf\CanonicalJsonTrait;

final class Key
{

    use CanonicalJsonTrait;

    private string $secretKey;

    private string $publicKey;

    public function __construct()
    {
        $keyPair = sodium_crypto_sign_keypair();

        $this->secretKey = sodium_crypto_sign_secretkey($keyPair);
        $this->publicKey = sodium_crypto_sign_publickey($keyPair);
    }

    public function sign(array $data): array
    {
        $data = self::encodeJson($data);

        $signature = sodium_crypto_sign_detached($data, $this->secretKey);

        return [
            'keyid' => $this->id(),
            'sig' => sodium_bin2hex($signature),
        ];
    }

    public function toArray(): array
    {
        return [
            'keytype' => 'ed25519',
            'scheme' => 'ed25519',
            'keyval' => [
                'public' => sodium_bin2hex($this->publicKey),
            ],
            'keyid_hash_algorithms' => ['sha256', 'sha512'],
        ];
    }

    public function id(): string
    {
        return hash('sha256', self::encodeJson($this->toArray()));
    }

    public static function fromStaticList(): self
    {
        static $index = 0;
        static $list = [
            [
                '441601a31124457e04a3afbd4d493e9286282180b521951a203a301081a9caa417f02c035a85ab873686fb5be430cee6169e5368e4f3af991d55f538e106af51',
                '17f02c035a85ab873686fb5be430cee6169e5368e4f3af991d55f538e106af51',
            ],
            [
                '8c9ecc1300cedc82bcb93c3396514c29983bbd7ebab5d613af459bc800683a0b4f17be8e11cb0d58f389b9ed84628515758b3e7eab807be1ba5b3698b5deb525',
                '4f17be8e11cb0d58f389b9ed84628515758b3e7eab807be1ba5b3698b5deb525',
            ],
            [
                '99dd73ac5ef727a75acf1b0a4051a0293a4e9eca2ad7ff7b7d1639e931c27820497bd07ccd73e9b7294c5e4da983aaf1624ff5f0feb3afb38cf53a83e1f5c085',
                '497bd07ccd73e9b7294c5e4da983aaf1624ff5f0feb3afb38cf53a83e1f5c085',
            ],
            [
                '53748c9438c94b976de0cb5193437bd91cceadce0c93df890310ac4318e659c2f6ef30a27579045ccaf2dff6413726925116178e7e4596021703c0d2665c6428',
                'f6ef30a27579045ccaf2dff6413726925116178e7e4596021703c0d2665c6428',
            ],
            [
                '0970505f729ce26a4079347cb48db10551ed7a2135f72ea083f7d6f69b27deff0962b59394ba41cc076380b4b127dee2586a2059e796d919b4579406db91a02f',
                '0962b59394ba41cc076380b4b127dee2586a2059e796d919b4579406db91a02f',
            ],
            [
                '29f5be2bc5f67d19c06cf4267cf91b086f07ecea30f3835e911c11e098f660b1dec1ed73acdc2e89411fbefb6d8e39d6f0a5f46cdfd65916251a51d1d94673b4',
                'dec1ed73acdc2e89411fbefb6d8e39d6f0a5f46cdfd65916251a51d1d94673b4',
            ],
            [
                '20dbe63a224436ef4f6d9db95e548e1c37497347f8dd178e84dff8c6edf98d1ea9e7f804d3a7e789bc3248e7deca804b90ab40dac195c28b2dc0600720a74643',
                'a9e7f804d3a7e789bc3248e7deca804b90ab40dac195c28b2dc0600720a74643',
            ],
            [
                '2e3f4822480bed08f14d8b5c64ae89cc738377f4fc06e556c6814a1798e06a6d42388af01a33248aea4162cbd1086baf2ad8c327b90558d211d3333331f1c065',
                '42388af01a33248aea4162cbd1086baf2ad8c327b90558d211d3333331f1c065',
            ],
            [
                'e04999578bcb674500256b4246fa45f97d56f03616b6a9479f53d8e6ed7d48e522af41b5cfab58e42fd049c855165dca9f4e98432d2e88fe0b1f2f450cfa82b7',
                '22af41b5cfab58e42fd049c855165dca9f4e98432d2e88fe0b1f2f450cfa82b7',
            ],
            [
                '9953fc10353464e00b46870e306c373a6551540b1521e52b2cc64087260d29088b2b2556e69585e4cfe712768fba733335c1ddfa212ca9247bf21ea70d20cfc4',
                '8b2b2556e69585e4cfe712768fba733335c1ddfa212ca9247bf21ea70d20cfc4',
            ],
            [
                'f61c4a0ce54c0bd607db75a9f6f3e417b38abaee3fa9e00fad140b8f7c2761c95ccd1a9ae3c785e40b47bef792f1fd24bf86e25fe1efb5a498aaed391f8396ca',
                '5ccd1a9ae3c785e40b47bef792f1fd24bf86e25fe1efb5a498aaed391f8396ca',
            ],
            [
                '1421ea03806f22314bcb1848bf6d4e3823d4432f1c08f1e4fbd17bcd6dcd3085c88668a54900f36c25050ae690397cd047e45f9d7aef30f41b095ec6d594914e',
                'c88668a54900f36c25050ae690397cd047e45f9d7aef30f41b095ec6d594914e',
            ],
            [
                'ae34fa41f28ab6e550550b842b1ace1f17e4d6afbdac6d4335961ae1df18684c5778287e25fa03287a84d8bbe705db16449466b32b0afb92f605c27afa482ee1',
                '5778287e25fa03287a84d8bbe705db16449466b32b0afb92f605c27afa482ee1',
            ],
            [
                '271fde61aca4f1c85f8e064457d7de0327f297beb782f2e2ed339624f10e01a414b5106b1369967da2380313ecbb198656fe4df1509fa4dc91067117af3f7593',
                '14b5106b1369967da2380313ecbb198656fe4df1509fa4dc91067117af3f7593',
            ],
            [
                'e03c1ddf4eb555b9690735a7745344a251d8fb83eee8571834a321380e542e0e38ba67a8d727f76c6110a85a875efa3ffb40ab5f4caa36a5cf11ff82fc92086c',
                '38ba67a8d727f76c6110a85a875efa3ffb40ab5f4caa36a5cf11ff82fc92086c',
            ],
            [
                '90b999644c601c09a991a3d3607ed70ceb304aef25a85da856456506c16a8467a8a5197993cf900f12c8c49ba5a45d294af5c2a8b9dea4311345a85381adfafa',
                'a8a5197993cf900f12c8c49ba5a45d294af5c2a8b9dea4311345a85381adfafa',
            ],
            [
                '4659c08cbdb4bbd6eb59b62aae02603522532c3e426bb4a5a756e8a8bf261aca07a0f473e41f7c5529afe3a9fa10b125206e49f6628c365905590a0d3b2b5a62',
                '07a0f473e41f7c5529afe3a9fa10b125206e49f6628c365905590a0d3b2b5a62',
            ],
            [
                '47618ad59f190844b45191792b0e5b5ebcd71bf0b4e30da63a303e86f7e7334e8f0c8c26ad7dfcdb8a44753c3e7560e7ef201b85345fc00ff776d99a56fd9aa9',
                '8f0c8c26ad7dfcdb8a44753c3e7560e7ef201b85345fc00ff776d99a56fd9aa9',
            ],
            [
                '0bce0653b91a13879ab301ade7f7494b8bb3caf8b3ab7d795b49a7afdba88c41faf2dbdf16aa5745d89ab4902d1db1c36ad2d39adf52c4e9c1e1cb4ea9ae53e3',
                'faf2dbdf16aa5745d89ab4902d1db1c36ad2d39adf52c4e9c1e1cb4ea9ae53e3',
            ],
            [
                '547677a6b709d050992e4d3f522f8eef8398d0595bd4fbab82f5b71bcd0487e1a6a934c27ea6d474c599b103dc0912a478f679a843d4cc5e089523583ab4fc6f',
                'a6a934c27ea6d474c599b103dc0912a478f679a843d4cc5e089523583ab4fc6f',
            ],
        ];

        $key = new self();
        [$key->secretKey, $key->publicKey] = array_map('sodium_hex2bin', $list[$index]);

        $index++;
        if ($index === count($list)) {
            $index = 0;
        }
        return $key;
    }
}
