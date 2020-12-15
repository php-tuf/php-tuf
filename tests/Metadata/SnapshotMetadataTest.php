<?php

namespace Tuf\Tests\Metadata;

use Tuf\Exception\MetadataException;
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
    protected static function callCreateFromJson(string $json) : MetadataBase
    {
        return SnapshotMetadata::createFromJson($json);
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
     * @throws \Tuf\Exception\MetadataException
     *
     * @todo Change this more generic `testUnsupportedfields()` in base class.
     */
    public function testUnsupportedLength() {
        $metadata = json_decode($this->localRepo[$this->validJson], true);
        $metadata['signed']['meta']['targets.json']['length'] = 1;
        $expectedMessage = preg_quote("Object(ArrayObject)[signed][meta][targets.json][length]", '/');
        $expectedMessage .= ".*This field was not expected.";
        $this->expectException(MetadataException::class);
        $this->expectExceptionMessageMatches("/$expectedMessage/s");
        static::callCreateFromJson(json_encode($metadata));
    }
}
