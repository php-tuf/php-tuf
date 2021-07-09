<?php

namespace Tuf\Tests\Metadata;

use Tuf\Exception\MetadataException;

/**
 * Test method for testing unsupported fields.
 */
trait UnsupportedFieldsTestTrait
{
    /**
     * Tests that unsupported fields throw an exception.
     *
     * @param array $unsupportedField
     *   The array of nested keys for the unsupported field.
     * @param mixed $fieldValue
     *   The field value to set.
     * @param string $expectedMessage
     *   The expected regex exception message.
     *
     * @dataProvider providerUnsupportedFields
     *
     * @return void
     */
    public function testUnsupportedFields(array $unsupportedField, $fieldValue, string $expectedMessage): void
    {
        $metadata = json_decode($this->clientStorage[$this->validJson], true);
        static::nestedChange($unsupportedField, $metadata, $fieldValue);
        self::expectException(MetadataException::class);
        self::expectExceptionMessageMatches("/$expectedMessage/s");
        static::callCreateFromJson(json_encode($metadata));
    }

    /**
     * Data provider for testUnsupportedFields().
     *
     * @return array[]
     *  The test cases.
     */
    abstract public function providerUnsupportedFields(): array;
}
