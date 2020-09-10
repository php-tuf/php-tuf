<?php

namespace Tuf\Tests\Metadata;

use Tuf\Exception\MetadataException;
use Tuf\JsonNormalizer;
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
     * Calls createFromJson() for the test class.
     *
     * @param string $json
     *   The json string.
     *
     * @return void
     */
    abstract protected static function callCreateFromJson(string $json);

    /**
     * Tests for valid metadata.
     *
     * @return void
     */
    public function testValidMetaData()
    {
        $this->expectNotToPerformAssertions();
        static::callCreateFromJson($this->localRepo[$this->validJson]);
    }

    /**
     * Tests that validation fails on invalid type.
     *
     *  @return void
     */
    public function testInvalidType()
    {
        $metadata = json_decode($this->localRepo[$this->validJson], true);
        $metadata['signed']['_type'] = 'invalid_type_value';
        $expectedMessage = preg_quote("Array[signed][_type]", '/');
        $expectedMessage .= ".*This value should be equal to \"{$this->expectedType}\"";
        $this->expectException(MetadataException::class);
        $this->expectExceptionMessageMatches("/$expectedMessage/s");
        static::callCreateFromJson(JsonNormalizer::asNormalizedJson($metadata));
    }

    /**
     * Tests for metadata with a missing field.
     *
     * @param string $expectedField
     *   The name of the field. Nested fields indicated with ":".
     *
     * @param string|null $exception
     *
     *   A different exception message to expect.
     *
     * @return void
     *
     * @dataProvider providerExpectedField
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
        static::callCreateFromJson($json);
    }

    /**
     * Unset a nested array element.
     *
     * @param array $keys
     *   Ordered keys to the value to unset.
     * @param array $data
     *   The array to modify.
     *
     * @return void
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
     *   The name of the field. Nested fields indicated with ":".
     *
     * @param string $expectedType
     *   The type of the field.
     *
     * @return void
     *
     * @dataProvider providerValidField
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
        static::callCreateFromJson($json);
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
     *
     * @return void
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
     *
     * @return array
     *   Array of arrays of expected field name, and optional exception message.
     */
    public function providerExpectedField() : array
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
     *
     * @return array
     *   Array of arrays of expected field name, and field data type.
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
