<?php

namespace jonathanraftery\Bullhorn\Rest;

use GuzzleHttp\Exception\ClientException;
use jonathanraftery\Bullhorn\Rest\Authentication\Client as AuthClient;
use jonathanraftery\Bullhorn\Rest\Authentication\Exception\InvalidRefreshTokenException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Request as HttpRequest;
use GuzzleHttp\Psr7\Uri;

class Client
{
    protected $authClient;
    protected $httpClient;
    protected $options;

	/**
	 * Client constructor
	 *
	 * @param string $clientId
	 * @param string $clientSecret
	 * @param null $dataStore
	 * @param array $options
	 */
    public function __construct(
        string $clientId,
        string $clientSecret,
        $dataStore = null,
        array $options = []
    ) {
        $this->authClient = new AuthClient(
            $clientId,
            $clientSecret,
            $dataStore
        );

        $defaultOptions = [
            'autoRefresh' => true,
            'maxSessionRetry' => 5
        ];

        $this->options = array_merge(
            $defaultOptions,
            $options
        );
    }

	/**
	 * Creates a new session
	 *
	 * @param string $username
	 * @param string $password
	 * @param array $options
	 */
    public function initiateSession(
        string $username,
        string $password,
        array $options = []
    ) {
        $gotSession = false;
        $tries = 0;

        do {
            try {
                $this->authClient->initiateSession(
                    $username,
                    $password,
                    $options
                );

                $gotSession = true;
            } catch (ClientException $e) {
                ++$tries;

                if ($tries >= $this->options['maxSessionRetry']) {
                    throw $e;
				}
            }
        } while (!$gotSession);

        $this->httpClient = new HttpClient([
            'base_uri' => $this->authClient->getRestUrl()
        ]);
    }

	/**
	 * Refreshes the session
	 *
	 * @param array $options
	 * @throws InvalidRefreshTokenException
	 */
    public function refreshSession(array $options = [])
    {
        $this->authClient->refreshSession($options);

        $this->httpClient = new HttpClient([
            'base_uri' => $this->authClient->getRestUrl()
        ]);
    }

	/**
	 * Refreshes the existing session
	 * or creates a new session
	 *
	 * @param $username
	 * @param $password
	 * @param array $options
	 */
    public function refreshOrInitiateSession(
        string $username,
        string $password,
        array $options = []
    ) {
        try {
            $this->refreshSession($options);
        } catch (InvalidRefreshTokenException $e) {
            $this->initiateSession(
                $username,
                $password,
                $options
            );
        }
    }

	/**
	 * Determines if the current session is valid
	 *
	 * @return bool
	 */
    public function sessionIsValid()
    {
        return $this->authClient->sessionIsValid();
    }

	/**
	 * Performs a request
	 *
	 * @param string $method
	 * @param string $url
	 * @param array $options
	 * @param array $headers
	 * @return mixed
	 * @throws InvalidRefreshTokenException
	 */
    public function request(
        string $method,
        string $url,
        array $options = [],
        array $headers = []
    ) {
        $fullHeaders = $this->appendDefaultHeadersTo($headers);
        $options['headers'] = $fullHeaders;

        try {
            $response = $this->httpClient
				->request($method, $url, $options);

            $responseBody = $response->getBody()
				->getContents();

            return json_decode($responseBody);
        } catch (ClientException $e) {
            if ($this->options['autoRefresh']) {
                return $this->handleRequestException(
                	compact('method', 'url', 'options', 'headers'),
					$e
				);
            }

			throw $e;
        }
    }

	/**
	 * Performs multiple requests asynchronously
	 *
	 * @param $requests
	 * @return array
	 * @throws \Throwable
	 */
    public function requestMultiple($requests)
    {
        $promises = array_map(function ($request) {
        	return $this->httpClient->sendAsync($request);
		}, $requests);

        $responses = Promise\unwrap($promises);

        return $responses;
    }

	/**
	 * @param $method
	 * @param $url
	 * @param array $parameters
	 * @param array $headers
	 * @return HttpRequest
	 */
    public function buildRequest(
        string $method,
        string $url,
        array $parameters = [],
        array $headers = []
    ) {
        $headers = $this->appendDefaultHeadersTo($headers);

        if ($method === 'GET') {
            $query = http_build_query($parameters);
            $uri = new Uri($url);
            $fullUri = $uri->withQuery($query);

            return new HttpRequest(
                $method,
                $fullUri,
                $headers
            );
        } else {
            return new HttpRequest(
                $method,
                $url,
                $headers,
                http_build_query($parameters)
            );
        }
    }

	/**
	 * Magic method to interact with the given entity type
	 *
	 * @param $entityType
	 * @return Entity
	 */
    public function __get($entityType)
    {
        return new Entity($this, $entityType);
    }

	/**
	 * Handles a request exception,
	 * automatically refreshing the session
	 *
	 * @param $request
	 * @param $exception
	 * @return mixed
	 * @throws InvalidRefreshTokenException
	 */
    protected function handleRequestException($request, $exception)
    {
        if ($exception->getResponse()->getStatusCode() == 401) {
            return $this->handleExpiredSessionOnRequest($request);
		}

		throw $exception;
    }

	/**
	 * Refreshes the session and tries the request again
	 *
	 * @param $request
	 * @return mixed
	 * @throws InvalidRefreshTokenException
	 */
    protected function handleExpiredSessionOnRequest($request)
    {
        $this->refreshSession();

        return $this->request(
            $request['method'],
            $request['url'],
            $request['options'],
            $request['headers']
        );
    }

	/**
	 * Merges the token into the headers
	 *
	 * @param $headers
	 * @return array
	 */
    protected function appendDefaultHeadersTo($headers)
    {
        $defaultHeaders = [
            'BhRestToken' => $this->authClient->getRestToken()
        ];

        return array_merge(
            $headers,
            $defaultHeaders
        );
    }

	/**
	 * Creates an EventSubscription instance
	 *
	 * @param string $name
	 * @return EventSubscription
	 */
	public function eventSubscription(string $name)
	{
		return new EventSubscription($this, $name);
    }

	/**
	 * Alias for eventSubscription
	 *
	 * @param string $name
	 * @return EventSubscription
	 */
	public function subscription(string $name)
	{
		return $this->eventSubscription($name);
    }
}
