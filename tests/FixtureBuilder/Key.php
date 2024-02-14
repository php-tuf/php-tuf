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
}
