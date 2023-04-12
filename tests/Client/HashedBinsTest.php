<?php

namespace Tuf\Tests\Client;

use Tuf\Tests\ClientTestBase;

class HashedBinsTest extends ClientTestBase
{
    public function test(): void
    {
        $this->loadClientAndServerFilesFromFixture('HashedBins');

        $updater = $this->getUpdater();
        $updater->download('a.txt');
    }
}
