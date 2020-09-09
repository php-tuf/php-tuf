<?php

namespace Tuf\Tests\Metadata;

use Tuf\Metadata\SnapshotMetadata;

class SnapshotMetadataTest extends MetaDataBaseTest
{

    /**
     * {@inheritdoc}
     */
    protected $validJson = '1.snapshot.json';

    /**
     * {@inheritdoc}
     */
    protected $testClass = SnapshotMetadata::class;

    /**
     * {@inheritdoc}
     */
    protected $expectedType = 'snapshot';

    public function providerExpectedField()
    {
        $data = parent::providerExpectedField();
        $data[] = ['signed:meta'];
        $data[] = ['signed:meta:targets.json', 'This collection should contain 1 element or more.'];
        $data[] = ['signed:meta:targets.json:version'];
        return $data;
    }

    public function providerValidField()
    {
        $data = parent::providerValidField();
        $data[] = ['signed:meta', 'array'];
        $data[] = ['signed:meta:targets.json', 'array'];
        $data[] = ['signed:meta:targets.json:version', 'int'];
        return $data;
    }

}
