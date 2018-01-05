<?php
namespace PunktDe\Behat\Guzzle\Assertion;

/*
 *  (c) 2017 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert as PhpUnitAssert;

class JsonAssertion
{
    /**
     * @param string $responseBody
     * @param TableNode $table
     * @return void
     * @throws \Exception
     */
    public static function assertJsonFieldsOfResponseByTable(string $responseBody, TableNode $table)
    {
        $data = json_decode($responseBody, true);

        if ($data === null) {
            throw new \Exception("The response could not be parsed to JSON: \n" . $body, 1432278325);
        }

        foreach ($table->getRowsHash() as $key => $value) {
            try {
                PhpUnitAssert::assertEquals(self::convertBooleanStringsToRealBooleans($value), self::getArrayContentByArrayAndNamespace($data, $key));
            } catch (\PHPUnit_Framework_ExpectationFailedException $exception) {
                $message = sprintf("\n%s\n\nThe API response should contain element '%s' with value '%s'\n--\nbut it is: \n--\n%s\n--\n", $exception->getMessage(), $key, $value, $body);
                throw new \Exception($message, 1407864528);
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
