<?php

namespace Tuf\Tests\Client;

use Tuf\Tests\ClientTestBase;
use Tuf\Tests\FixtureBuilder\Fixture;

class HashedBinsTest extends ClientTestBase
{
    /**
     * @testWith [true]
     *   [false]
     */
    public function test(bool $consistentSnapshot): void
    {
        $fixture = new Fixture();
        $fixture->root->consistentSnapshot = $consistentSnapshot;
        $fixture->publish(true);
        $fixture->createHashBins(8, ['terminating' => true]);
        for ($c = 97; $c < 123; $c++) {
            $path = $fixture->createTarget(chr($c) . '.txt', null);
            $fixture->addToHashBin($path);
        }
        $fixture->invalidate();
        $fixture->publish();
        $this->loadClientAndServerFilesFromFixture($fixture, [
            'root' => 1,
            'timestamp' => 1,
            'snapshot' => 1,
            'targets' => 1,
        ]);

        // The fixture contains targets a.txt through z.txt (ASCII 97 through
        // 122), distributed across hashed bins.
        $targets = array_map(fn (int $c) => chr($c) . '.txt', range(97, 122));

        $updater = $this->getUpdater();
        // We should be able to download every single target without trouble.
        foreach ($targets as $name) {
            // By default, the fixture builder puts "Contents: FILENAME" into
            // the target files it creates.
            $this->assertSame("Contents: $name", $updater->download($name)->wait()->getContents());
        }
    }
}
