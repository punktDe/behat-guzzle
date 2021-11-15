<?php
namespace PunktDe\Behat\Guzzle\Assertion;

/*
 *  (c) 2017 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert as PhpUnitAssert;
use Neos\Utility\Arrays;

class JsonAssertion
{
    /**
     * @param string $responseBody
     * @param TableNode $table
     * @param bool $strict
     * @return void
     * @throws \Exception
     */
    public static function assertJsonFieldsOfResponseByTable(string $responseBody, TableNode $table, $strict = false)
    {
        $data = json_decode($responseBody, true);
        if ($data === null) {
            throw new \Exception("The response could not be parsed to JSON: \n" . $responseBody, 1432278325);
        }

        $rowHash = $table->getRowsHash();

        if ($strict === true) {
            $compareArray = [];

            foreach ($rowHash as $key => $value) {
                $compareArray = Arrays::setValueByPath($compareArray, $key, self::convertBooleanStringsToRealBooleans($value));
            }

            PhpUnitAssert::assertEquals($compareArray, $data);
        } else {
            foreach ($rowHash as $key => $value) {
                try {
                    PhpUnitAssert::assertEquals(self::convertBooleanStringsToRealBooleans($value), self::getArrayContentByArrayAndNamespace($data, $key));
                } catch (\PHPUnit_Framework_ExpectationFailedException $exception) {
                    $message = sprintf("\n%s\n\nThe API response should contain element '%s' with value '%s'\n--\nbut it is: \n--\n%s\n--\n",
                        $exception->getMessage(), $key, $value, $responseBody);
                    throw new \Exception($message, 1407864528);
                }
            }
        }
    }

    /**
     * Returns part of an array according to given namespace
     *
     * @param array $returnArray
     * @param string $namespace
     * @return array
     */
    public static function getArrayContentByArrayAndNamespace(array $returnArray, string $namespace)
    {
        if (!$namespace) {
            return $returnArray;
        }
        if (!is_array($returnArray)) {
            return [];
        }

        $namespaceArray = explode('.', $namespace);

        foreach ($namespaceArray as $namespaceChunk) {
            if (is_array($returnArray) && array_key_exists($namespaceChunk, $returnArray)) {
                $returnArray = $returnArray[$namespaceChunk];
            } else {
                return [];
            }
        }
        return $returnArray;
    }

    /**
     * @param mixed $value
     * @return bool|string
     */
    protected static function convertBooleanStringsToRealBooleans(string $value)
    {
        switch ($value) {
            case ('true'):
                return true;
            case ('false'):
                return false;
            default:
                return $value;
        }
    }
}
