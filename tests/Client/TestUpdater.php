<?php

namespace Tuf\Tests\Client;

use Tuf\Client\RepoFileFetcherInterface;
use Tuf\Client\Updater;
use Tuf\Helper\Clock;
use Tuf\Tests\TestHelpers\TestClock;

/**
 * An updater class for testing that allows specifying the Clock instance to use.
 */
class TestUpdater extends Updater
{
    public function __construct(RepoFileFetcherInterface $repoFileFetcher, array $mirrors, \ArrayAccess $durableStorage, Clock $testClock)
    {
        parent::__construct($repoFileFetcher, $mirrors, $durableStorage);
        $this->clock = $testClock;
    }
}
