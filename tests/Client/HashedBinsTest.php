<?php

namespace Tuf\Tests\Client;

use Tuf\Tests\ClientTestBase;

class HashedBinsTest extends ClientTestBase
{
    public function test(): void
    {
        $this->loadClientAndServerFilesFromFixture('HashedBins');

        $updater = $this->getUpdater();
        // The fixture contains a.txt through z.txt, distributed across hashed
        // bins. We should be able to download every single one without any
        // trouble.
        foreach (array_map('chr', range(97, 122)) as $c) {
            $this->assertSame("Contents: $c.txt", $updater->download("$c.txt")->getContents());
        }
    }
}
