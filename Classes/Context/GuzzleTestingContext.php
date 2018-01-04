<?php
namespace PunktDe\Behat\Guzzle\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Testwork\Suite\Exception\SuiteConfigurationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Response;
use Neos\Flow\Http\Client\CurlEngineException;
use Neos\Utility\Arrays;
use Neos\Utility\Files;
use PHPUnit\Framework\Assert;

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
     * @When /^I do a :method request on "([^"]+)"(?: with parameters)?$/
     */
    public function iDoARequestOnWithParameters(string $method, string $url, TableNode $parameters = null)
    {
        $options = [];

        $requestParameters = [];

        if ($parameters !== null) {
            foreach ($parameters->getRowsHash() as $k => $v) {
                $requestParameters = Arrays::setValueByPath($requestParameters, $k, $v);
            }
        }

        try {
            if ($method === 'POST') {

                $options['multipart'] = [];

                foreach ($requestParameters as $name => $requestParameter) {
                    if ($this->isUploadFile($requestParameter)) {
                        $requestParameter = fopen(Files::concatenatePaths([$this->workingDirectory, substr($requestParameter, 1)]), 'r');
                    }

                    $options['multipart'][] = ['name' => $name, 'contents' => $requestParameter];
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
     * @param string $needle
     * @param string $ignoreCaseString
     *
     * @Then /^the result does not contain "(?P<needle>(?:[^"]|\\")*)"(?P<ignoreCaseString>(?: ignoring case))?$/
     */
    public function theResultDoesNotContain($needle, $ignoreCaseString = '')
    {
        $ignoreCase = strlen($ignoreCaseString) > 0;

        Assert::assertNotContains($needle, $this->lastResponse->getBody(), '', $ignoreCase);
    }

    /**
     * @Then the result should be valid json
     */
    public function theResultIsValidJson()
    {
        $data = json_decode($this->lastResponse->getBody(), true);
        Assert::assertNotNull($data, 'API did not return a valid JSON-String.');
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
}
