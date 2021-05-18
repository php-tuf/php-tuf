<?php


namespace Tuf\Tests\Client;

use Tuf\Client\RepoFileFetcherInterface;
use Tuf\Client\Updater;
use Tuf\Tests\TestHelpers\TestClock;

/**
 * An updater class for testing uses \Tuf\Tests\TestHelpers\TestClock class.
 */
class TestUpdater extends Updater
{

    public function __construct(RepoFileFetcherInterface $repoFileFetcher, array $mirrors, \ArrayAccess $durableStorage)
    {
        parent::__construct($repoFileFetcher, $mirrors, $durableStorage);
        $this->clock = new TestClock();
    }
}
