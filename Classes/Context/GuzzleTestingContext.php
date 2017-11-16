<?php

namespace PunktDe\Testing\Api\Context;

/*
 * This file is part of the PunktDe.Testing.Api package.
 */

use Behat\Behat\Context\Context;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Response;
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
     * @param $baseUrl
     */
    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
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
     * @When I do a :method request on :url
     *
     * @param string $method
     * @param string $url
     */
    public function iDoARequestOnWithParameters($method, $url)
    {
        try {
            $this->lastResponse = $this->client->request($method, $url);
        } catch (BadResponseException $serverException) {
            // even if an HTTP 5xx/4xx error occurs, we record the response.
            $this->lastResponse = $serverException->getResponse();
        }
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

}