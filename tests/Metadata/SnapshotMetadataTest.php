<?php

namespace Tuf\Tests\Metadata;

use Tuf\Metadata\MetadataBase;
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
    protected $expectedType = 'snapshot';

    /**
     * {@inheritdoc}
     */
    protected function callCreateFromJson(string $json) : MetadataBase
    {
        return SnapshotMetadata::createFromJson($json, $this->verifier);
    }
    /**
     * {@inheritdoc}
     */
    public function providerExpectedField() : array
    {
        $data = parent::providerExpectedField();
        $data[] = ['signed:meta'];
        $data[] = ['signed:meta:targets.json', 'This collection should contain 1 element or more.'];
        $data[] = ['signed:meta:targets.json:version'];
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function providerValidField() : array
    {
        $data = parent::providerValidField();
        $data[] = ['signed:meta', 'array'];
        $data[] = ['signed:meta:targets.json', 'array'];
        $data[] = ['signed:meta:targets.json:version', 'int'];
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function providerOptionalFields()
    {
        $data = parent::providerOptionalFields();
        $data[] = [
            'signed:meta:targets.json:length',
            789,
        ];
        return static::getKeyedArray($data);
    }
}
