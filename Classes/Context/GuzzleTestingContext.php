<?php

namespace PunktDe\Behat\Guzzle\Context;

/*
 *  (c) 2017 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Testwork\Suite\Exception\SuiteConfigurationException;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Response;
use Neos\Flow\Http\Client\CurlEngineException;
use Neos\Utility\Arrays;
use Neos\Utility\Files;
use PHPUnit\Framework\Assert;
use PunktDe\Behat\Guzzle\Assertion\JsonAssertion;
use PunktDe\Import\Exception;

class GuzzleTestingContext implements Context
{
    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Response
     */
    protected $lastResponse;

    /**
     * @var string
     */
    protected $workingDirectory;

    /**
     * @param string $baseUrl
     * @param string $workingDirectory
     */
    public function __construct(string $baseUrl, string $workingDirectory = '/tmp/')
    {
        $this->baseUrl = $baseUrl;

        $this->workingDirectory = realpath($workingDirectory);
        if (!is_dir($workingDirectory)) {
            throw new SuiteConfigurationException(sprintf('The working directory %s was not found.', $workingDirectory), 1432736667);
        }
    }

    /**
     * We reset the client before every scenario, so that there are no side-effects (like cookies) between scenarios.
     *
     * @BeforeScenario
     */
    public function initializeClient()
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'cookies' => true,
            'headers' => [
                'User-Agent' => 'FancyPunktDeGuzzleTestingAgent'
            ]
        ]);
    }

    /**
     * @When /^I do a ([^"]+) request on "([^"]+)"(?: with parameters)?$/
     */
    public function iDoARequestOnWithParameters(string $method, string $url, TableNode $parameters = null)
    {
        $options = [];

        $requestParameters = [];

        if ($parameters !== null) {
            foreach ($parameters->getRowsHash() as $key => $value) {
                $requestParameters = Arrays::setValueByPath($requestParameters, $key, $value);
            }
        }

        try {
            if ($method === 'POST') {

                $payload = [];
                $transferMode = 'form_params';

                foreach ($requestParameters as $name => $requestParameter) {
                    if ($this->isUploadFile($requestParameter)) {
                        $transferMode = 'multipart';

                        $filePath = Files::concatenatePaths([$this->workingDirectory, substr($requestParameter, 1)]);
                        $requestParameter = fopen($filePath, 'r');

                        if($requestParameter === false) {
                            throw new \Exception('Unable to open file pointer to file '. $filePath, 1516221705);
                        }
                    }

                    $payload[$name] = $requestParameter;
                }

                if($transferMode === 'multipart') {
                    foreach ($payload as $entryName => $payloadData) {
                        $options[$transferMode][] = [
                            'name' => $entryName,
                            'contents' => $payloadData
                        ];
                    }
                } else {
                    $options[$transferMode] = $payload;
                }


            } elseif ($method === 'GET') {
                $options['query'] = $requestParameters;
            }

            $this->lastResponse = $this->client->request($method, $url, $options);

        } catch (BadResponseException $serverException) {
            // even if an HTTP 5xx/4xx error occurs, we record the response.
            $this->lastResponse = $serverException->getResponse();
        }
    }

    /**
     * @Then the api response should be :expectedText
     */
    public function theApiResponseShouldBe($expectedText)
    {
        $responseBody = (string)$this->lastResponse->getBody();
        ASsert::assertEquals($responseBody, $expectedText, sprintf("The API response should be exactly \n--\n%s\n--\nbut it is: \n--\n%s\n--\n", $expectedText, $responseBody));
    }

    /**
     * @Then the api response should be valid json
     * @Then the result should be valid json
     */
    public function theApiResponseIsValidJson()
    {
        $responseBody = $this->lastResponse->getBody();
        $data = json_decode($responseBody, true);
        Assert::assertNotFalse($data, 'API did not return a valid JSON-String.');
    }

    /**
     * @Then the api response should contain :expectedText
     * @Then /^sollte (?:der|die|das) API Response folgende(?:|n) "([^"]*)" enthalten$/
     * @Then /^sollte (?:der|die|das) API Response "([^"]+)" enthalten$/
     */
    public function theApiResponseShouldContain($expectedText)
    {
        $responseBody = (string) $this->lastResponse->getBody()->getContents();
        Assert::assertNotFalse(strstr($responseBody, $expectedText), sprintf("The API response should contain \n--\n%s\n--\nbut it is: \n--\n%s\n--\n", $expectedText, $responseBody));
    }

    /**
     * @Then the api response headers should contain :expectedText
     */
    public function theApiResponseHeadersShouldContain($expectedText)
    {
        $headers = $this->convertHeadersToString($this->lastResponse->getHeaders());
        Assert::assertNotFalse(strstr($headers, $expectedText), sprintf("The API response headers should contain %s, but it is: \n--\n%s\n--\n.", $expectedText, $headers));
    }

    /**
     * @Then the result status code is not an error code
     */
    public function theResultStatusCodeIsNotAnErrorCode()
    {
        $responseHttpCode = (string)$this->lastResponse->getStatusCode();
        $statusClass = substr($responseHttpCode, 0, 1);

        Assert::assertNotEquals('4', $statusClass,
            sprintf('HTTP status code for request was "%s", should not be 4xx', $responseHttpCode));
        Assert::assertNotEquals('5', $statusClass,
            sprintf('HTTP status code for request was "%s", should not be 5xx', $responseHttpCode));
    }

    /**
     * @Then the HTTP status code should be :statusCode
     */
    public function theHttpStatusCodeShouldBe($statusCode)
    {
        Assert::assertEquals((int)$statusCode, (int)$this->lastResponse->getStatusCode(), sprintf('The API response should return status code %s, but returned %s.', $statusCode, $this->lastResponse->getStatusCode()));
    }

    /**
     * @Then /^the api response should(?P<strictMode>(?: exactly))? return a JSON string with fields:/
     */
    public function theApiResponseShouldReturnJsonStringWithFields(TableNode $table, $strictMode = '')
    {
        $strict = strlen($strictMode) > 0;

        JsonAssertion::assertJsonFieldsOfResponseByTable((string)$this->lastResponse->getBody(), $table, $strict);
    }

    /**
     * @param string $needle
     * @param string $ignoreCaseString
     *
     * @Then /^the result does not contain "(?P<needle>(?:[^"]|\\")*)"(?P<ignoreCaseString>(?: ignoring case))?$/
     */
    public function theResultDoesNotContain($needle, $ignoreCaseString = '')
    {
        $ignoreCase = strlen($ignoreCaseString) > 0;

        Assert::assertNotContains($needle, $this->lastResponse->getBody()->getContents(), '', $ignoreCase);
    }

    /**
     * @Then the result does not contain a field :field in the path :path
     *
     * @param string $field
     * @param string $path
     */
    public function theResultDoesNotContainFieldInPath($field, $path)
    {
        $responseArray = json_decode($this->lastResponse->getBody(), true);
        $data = Arrays::getValueByPath($responseArray, $path);

        Assert::assertArrayNotHasKey($field, $data);
    }

    /**
     * @param string $requestParameter
     * @return bool
     * @throws \Exception
     */
    protected function isUploadFile($requestParameter): bool
    {
        if (is_string($requestParameter) && substr($requestParameter, 0, 1) === '@') {
            $filePath = Files::concatenatePaths([$this->workingDirectory, substr($requestParameter, 1)]);

            if (!file_exists($filePath)) {
                throw new \Exception(sprintf('The file at path %s does not exist.', $filePath), 1471523059);
            }

            return true;
        }
        return false;
    }

    /**
     * @param array $headers
     * @return string
     */
    protected function convertHeadersToString(array $headers): string
    {
        $headerString = '';
        foreach ($headers as $headerName => $header) {
            $headerString .= $headerName . ': ' .  $this->lastResponse->getHeaderLine($headerName) . PHP_EOL;
        }

        return $headerString;
    }

    /**
     * @When the api downloads the file :url to :targetPath
     */
    public function theApiDownloadsAFile($url, $targetPath)
    {
        $fileHandle = fopen(
            Files::concatenatePaths([$this->workingDirectory, $targetPath]),
            'w'
        );

        $contentForFile = '';
        try {
            $this->lastResponse = $this->client->request('GET', $url);
            $contentForFile = (string)$this->lastResponse->getBody();

        } catch (BadResponseException $serverException) {
            // even if an HTTP 5xx/4xx error occurs, we record the response.
            $this->lastResponse = $serverException->getResponse();
        }

        fwrite($fileHandle, $contentForFile);

        fclose($fileHandle);
    }

    /**
     * @Given cookie :cookieName is set to:
     * @Given cookie :cookieName is set to :value
     */
    public function cookieIsSet($cookieName, $value = null, TableNode $table = null)
    {
        $defaults = [
            'name' => $cookieName,
            'value' => $value,
            'domain' => $this->domain
        ];
        $data = ($table !== null ? array_merge($defaults, $table->getRowsHash()) : $defaults);

        $cookie = new SetCookie($data);

        $cookieJar = new CookieJar(true);
        $cookieJar->setCookie($cookie);
    }

    /**
     * @Then the api response header should not contain header :header
     */
    public function theApiResponseHeaderShouldNotContainHeader($header)
    {
        $lastHeader = $this->convertHeadersToString($this->lastResponse->getHeaders());
        Assert::assertFalse(strstr($lastHeader, $header), sprintf("The API response header should not contain header %s, but it does.", $header));
    }

    /**
     * @Then the api response header value :headerValue should match the regex :pattern
     */
    public function isRegexContainedInApiResponseHeader($headerValue, $pattern)
    {
        $header = $this->getHeaderByName($headerValue);
        if (preg_match($pattern, $header) !== 1) {
            throw new \Exception('The pattern "' . $pattern . '" is not contained in the value of Response Header ' . $headerValue);
        }
    }

    /**
     * @param string $headerName
     * @throws \Exception
     */
    protected function getHeaderByName($headerName)
    {
        $headerName = strtolower($headerName);
        if ($this->lastResponse->hasHeader($headerName)) {
            return $header = $this->lastResponse->getHeader($headerName);
        }
        throw new \Exception("No cookie header found in response.", 1421318511);
    }

}
