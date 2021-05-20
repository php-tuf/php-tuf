<?php

namespace Tuf\Tests\Metadata;

use Tuf\Metadata\MetadataBase;
use Tuf\Metadata\SnapshotMetadata;

class SnapshotMetadataTest extends MetadataBaseTest
{
    use UntrustedExceptionTrait;
    use UnsupportedFieldsTestTrait;

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
    protected static function callCreateFromJson(string $json): MetadataBase
    {
        return SnapshotMetadata::createFromJson($json);
    }
    /**
     * {@inheritdoc}
     */
    public function providerExpectedField(): array
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
    public function providerValidField(): array
    {
        $data = parent::providerValidField();
        $data[] = ['signed:meta', 'array'];
        $data[] = ['signed:meta:targets.json', 'array'];
        $data[] = ['signed:meta:targets.json:version', 'int'];
        return $data;
    }

    /**
     * Data provider for testUnsupportedFields().
     *
     * @return array[]
     *  The test cases.
     */
    public function providerUnsupportedFields(): array
    {
        $cases = [
            'length' => [['signed', 'meta', 'targets.json', 'length'], 1],
            'hashes' => [['signed', 'meta', 'targets.json', 'hashes'], []],
        ];
        foreach ($cases as $fieldName => &$case) {
            $expectedMessage = preg_quote("Object(ArrayObject)[signed][meta][targets.json][$fieldName]", '/');
            $expectedMessage .= ".*This field is not supported.";
            $case[] = $expectedMessage;
        }
        return $cases;
    }

    /**
     * Data provider for testUntrustedException().
     *
     * @return string[]
     *   The test cases for testUntrustedException().
     */
    public function providerUntrustedException(): array
    {
        $mockMetadata = $this->createMock(MetadataBase::class);
        return self::getKeyedArray([
            ['getFileMetaInfo', ['any-key']],
        ]);
    }
}
