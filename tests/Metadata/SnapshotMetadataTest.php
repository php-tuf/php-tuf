<?php

namespace Tuf\Tests\Metadata;

use Tuf\Exception\MetadataException;
use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\SnapshotMetadata;

class SnapshotMetadataTest extends MetaDataBaseTest
{
    use UntrustedExceptionTrait;

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
     * Tests that unsupported fields throw an exception.
     *
     * @param array $unsupportedField
     *   The array of nested keys for the unsupported field.
     * @param mixed $fieldValue
     *   The field value to set.
     *
     * @dataProvider providerUnsupportedFields
     *
     * @return void
     */
    public function testUnsupportedFields(array $unsupportedField, $fieldValue):void
    {
        $metadata = json_decode($this->localRepo[$this->validJson], true);
        $this->nestedChange($unsupportedField, $metadata, $fieldValue);
        $fieldName = array_pop($unsupportedField);
        $expectedMessage = preg_quote("Object(ArrayObject)[signed][meta][targets.json][$fieldName]", '/');
        $expectedMessage .= ".*This field is not supported.";
        $this->expectException(MetadataException::class);
        $this->expectExceptionMessageMatches("/$expectedMessage/s");
        static::callCreateFromJson(json_encode($metadata));
    }

    /**
     * Data provider for testUnsupportedFields().
     *
     * @return array[]
     *  The test cases.
     */
    public function providerUnsupportedFields():array
    {
        return [
            'length' => [['signed', 'meta', 'targets.json', 'length'], 1],
            'hashes' => [['signed', 'meta', 'targets.json', 'hashes'], []],
        ];
    }

    /**
     * Data provider for testUntrustedException().
     *
     * @return string[]
     *   The test cases for testUntrustedException().
     */
    public function providerUntrustedException():array
    {
        return self::getKeyedArray([
            ['getFileMetaInfo', ['any-key']],
            ['verifyNewMetaData', [$this->createMock(MetadataBase::class)]],
        ]);
    }
}
