<?php

namespace Tuf\Tests\Metadata;

use Tuf\Exception\MetadataException;
use Tuf\JsonNormalizer;
use Tuf\Metadata\MetadataBase;
use PHPUnit\Framework\TestCase;
use Tuf\Tests\TestHelpers\DurableStorage\MemoryStorageLoaderTrait;

/**
 * @coversDefaultClass \Tuf\Metadata\MetadataBase
 */
abstract class MetaDataBaseTest extends TestCase
{
    use MemoryStorageLoaderTrait;

    /**
     * @var \Tuf\Tests\TestHelpers\DurableStorage\MemoryStorage
     */
    protected $localRepo;

    /**
     * The valid json file.
     *
     * @var string
     */
    protected $validJson;

    /**
     * The class being tested.
     *
     * @var string
     */
    protected $testClass;

    /**
     * The expected metadata type;
     * @var string
     */
    protected $expectedType;


    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->localRepo = $this->memoryStorageFromFixture('tufclient/tufrepo/metadata/current');
    }

    /**
     * Tests for valid metadata.
     */
    public function testValidMetaData()
    {
        $this->expectNotToPerformAssertions();
        $this->testClass::createFromJson($this->localRepo[$this->validJson]);
    }

    public function testInvalidType() {
        $metadata = json_decode($this->localRepo[$this->validJson], true);
        $metadata['signed']['_type'] = 'invalid_type_value';
        $expectedMessage = preg_quote("Array[signed][_type]") . ".*This value should be equal to \"{$this->expectedType}\"";
        $this->expectException(MetadataException::class);
        $this->expectExceptionMessageMatches("/$expectedMessage/s");
        $this->testClass::createFromJson(JsonNormalizer::asNormalizedJson($metadata));
    }

    /**
     * Tests for metadata with a missing field.
     *
     * @param string $expectedField
     *
     * @param string $exception
     *   A different exception message to expect.
     *
     * @dataProvider providerExpectedField
     *
     */
    public function testMissingField(string $expectedField, string $exception = null)
    {
        $metadata = json_decode($this->localRepo[$this->validJson], true);
        $keys = explode(':', $expectedField);
        $fieldName = preg_quote('[' . implode('][', $keys) . ']', '/');
        $this->nestedUnset($keys, $metadata);
        $json = json_encode($metadata);
        $this->expectException(MetadataException::class);
        if ($exception) {
            $this->expectExceptionMessageMatches("/$exception/s");
        } else {
            $this->expectExceptionMessageMatches("/Array$fieldName.*This field is missing./s");
        }
        $this->testClass::createFromJson($json);
    }

    /**
     * Unset a nested array element.
     *
     * @param array $keys
     *   Ordered keys to the value to unset.
     * @param array $data
     *   The array to modify.
     */
    protected function nestedUnset(array $keys, array &$data)
    {
        $key = array_shift($keys);
        if ($keys) {
            $this->nestedUnset($keys, $data[$key]);
        } else {
            unset($data[$key]);
        }
    }

    /**
     * Tests for metadata with a field of invalid type.
     *
     * @param string $expectedField
     *
     * @param string $expectedType
     *
     * @dataProvider providerValidField
     *
     */
    public function testInvalidField(string $expectedField, string $expectedType)
    {
        $metadata = json_decode($this->localRepo[$this->validJson], true);
        $keys = explode(':', $expectedField);

        switch ($expectedType) {
            case 'string':
                $newValue = [];
                break;
            case 'int':
                $newValue = 'Abb';
                break;
            case 'array':
                $newValue = 3060;
                break;
        }

        $this->nestedChange($keys, $metadata, $newValue);
        $json = json_encode($metadata);
        $this->expectException(MetadataException::class);
        $this->expectExceptionMessageMatches("/This value should be of type $expectedType/s");
        $this->testClass::createFromJson($json);
    }

    /**
     * Change a nested array element.
     *
     * @param array $keys
     *   Ordered keys to the value to unset.
     * @param array $data
     *   The array to modify.
     * @param mixed $newValue
     *   The new value to set.
     */
    protected function nestedChange(array $keys, array &$data, $newValue)
    {
        $key = array_shift($keys);
        if ($keys) {
            $this->nestedChange($keys, $data[$key], $newValue);
        } else {
            $data[$key] = $newValue;
        }
    }

    /**
     * Dataprovider for testMissingField().
     */
    public function providerExpectedField()
    {
        return [
            ['signed'],
            ['signed:_type'],
            ['signed:expires'],
            ['signed:spec_version'],
            ['signed:version'],
            ['signatures'],
            ['signatures:0:keyid'],
            ['signatures:0:sig'],
        ];
    }

    /**
     * Dataprovider for testInvalidField().
     */
    public function providerValidField()
    {
        return [
            ['signed', 'array'],
            ['signed:_type', 'string'],
            ['signed:expires', 'string'],
            ['signed:spec_version', 'string'],
            ['signed:version', 'int'],
            ['signatures', 'array'],
            ['signatures:0:keyid', 'string'],
            ['signatures:0:sig', 'string'],
        ];
    }
}
