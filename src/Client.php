<?php namespace jonathanraftery\Bullhorn\Rest;

use GuzzleHttp\Exception\ClientException;
use jonathanraftery\Bullhorn\Rest\Authentication\AuthClient;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Request as HttpRequest;
use GuzzleHttp\Psr7\Uri;
use jonathanraftery\Bullhorn\Rest\Authentication\AuthClientCollaboratorKey;
use jonathanraftery\Bullhorn\Rest\Authentication\InvalidRefreshTokenException;

class Client
{
    protected $authClient;
    protected $httpClient;
    protected $options;

    public function __construct(
        $clientId,
        $clientSecret,
        $dataStore = null,
        array $options = []
    ) {
        $this->authClient = new AuthClient(
            $clientId,
            $clientSecret,
            [AuthClientCollaboratorKey::DataStore => $dataStore]
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

    public function initiateSession(
        $username,
        $password,
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
                if ($tries >= $this->options['maxSessionRetry'])
                    throw $e;
            }
        } while (!$gotSession);

        $this->httpClient = new HttpClient([
            'base_uri' => $this->authClient->getRestUrl()
        ]);
    }

    /**
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

    public function refreshOrInitiateSession(
        $username,
        $password,
        array $options = []
    ) {
        try {
            $this->refreshSession($options);
        }
        catch (InvalidRefreshTokenException $e) {
            $this->initiateSession(
                $username,
                $password,
                $options
            );
        }
    }

    public function sessionIsValid()
    {
        return $this->authClient->sessionIsValid();
    }

    public function request(
        $method,
        $url,
        $options = [],
        $headers = []
    ) {
        $fullHeaders = $this->appendDefaultHeadersTo($headers);
        $options['headers'] = $fullHeaders;

        try {
            $response = $this->httpClient->request(
                $method,
                $url,
                $options
            );
            $responseBody = $response->getBody()->getContents();
            return json_decode($responseBody);
        }
        catch (ClientException $e) {
            if ($this->options['autoRefresh']) {
                $request = [
                    'method' => $method,
                    'url' => $url,
                    'options' => $options,
                    'headers' => $headers
                ];
                return $this->handleRequestException($request, $e);
            }
            else
                throw $e;
        }
    }

    public function requestMultiple($requests)
    {
        $promises = [];
        foreach ($requests as $request)
            $promises[] = $this->httpClient->sendAsync($request);
        $responses = Promise\unwrap($promises);
        return $responses;
    }

    public function buildRequest(
        $method,
        $url,
        $parameters = [],
        $headers = []
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

    public function __get($resourceName)
    {
        $className = 'jonathanraftery\\Bullhorn\\Rest\\Resources\\' . $resourceName;
        return new $className($this);
    }

    private function handleRequestException($request, $exception)
    {
        if ($exception->getResponse()->getStatusCode() == 401)
            return $this->handleExpiredSessionOnRequest($request);
        else
            throw $exception;
    }

    private function handleExpiredSessionOnRequest($request)
    {
        $this->refreshSession();
        return $this->request(
            $request['method'],
            $request['url'],
            $request['options'],
            $request['headers']
        );
    }

    private function appendDefaultHeadersTo($headers)
    {
        $defaultHeaders = [
            'BhRestToken' => $this->authClient->getRestToken()
        ];
        return array_merge(
            $headers,
            $defaultHeaders
        );
    }
}
