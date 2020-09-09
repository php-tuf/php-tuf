<?php

namespace Tuf\Tests\Metadata;

use Tuf\Metadata\SnapshotMetadata;
use Tuf\Metadata\TimestampMetadata;

class TimestampMetadataTest extends MetaDataBaseTest
{

    /**
     * {@inheritdoc}
     */
    protected $validJson = '1.timestamp.json';

    /**
     * {@inheritdoc}
     */
    protected $testClass = TimestampMetadata::class;

    /**
     * {@inheritdoc}
     */
    protected $expectedType = 'timestamp';

    /**
     * {@inheritdoc}
     */
    public function providerExpectedField() : array
    {
        $data = parent::providerExpectedField();
        $data[] = ['signed:meta'];
        $data[] = ['signed:meta:snapshot.json', 'This collection should contain 1 element or more.'];
        $data[] = ['signed:meta:snapshot.json:version'];
        $data[] = ['signed:meta:snapshot.json:length'];
        $data[] = ['signed:meta:snapshot.json:hashes'];
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function providerValidField() : array
    {
        $data = parent::providerValidField();
        $data[] = ['signed:meta', 'array'];
        $data[] = ['signed:meta:snapshot.json', 'array'];
        $data[] = ['signed:meta:snapshot.json:version', 'int'];
        $data[] = ['signed:meta:snapshot.json:length', 'int'];
        $data[] = ['signed:meta:snapshot.json:hashes', 'array'];
        $data[] = ['signed:meta:snapshot.json:hashes:sha256', 'string'];
        $data[] = ['signed:meta:snapshot.json:hashes:sha512', 'string'];
        return $data;
    }
}
