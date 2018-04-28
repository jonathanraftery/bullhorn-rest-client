<?php

namespace jonathanraftery\Bullhorn\Rest;
use jonathanraftery\Bullhorn\Rest\Authentication\Client as AuthClient;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Request as HttpRequest;
use GuzzleHttp\Psr7\Uri;

class Client
{
    const MAX_ENTITY_REQUEST_COUNT = 100;
    private $authClient;
    private $httpClient;

    public function __construct(
        $clientId,
        $clientSecret,
        $username,
        $password,
        $dataStore = null
    ) {
        $this->authClient = new AuthClient(
            $clientId,
            $clientSecret,
            $dataStore
        );
        $this->initiateSession(
            $username,
            $password
        );
        $this->httpClient = new HttpClient([
            'base_uri' => $this->authClient->getRestUrl()
        ]);
    }

    public function sessionIsValid()
    { return $this->authClient->sessionIsValid(); }

    public function initiateSession($username, $password, array $options = [])
    { $this->authClient->initiateSession($username, $password, $options); }

    public function refreshSession(array $options = [])
    {
        $this->authClient->refreshSession($options);
        $this->httpClient = new HttpClient([
            'base_uri' => $this->authClient->getRestUrl()
        ]);
    }

    public function search(
        $entityName,
        $parameters = [],
        $headers = []
    ) {
        return $this->request(
            'GET',
            'search/' . $entityName,
            $parameters,
            $headers
        );
    }

    public function eventSubscription(
        $method,
        $subscriptionName,
        $parameters = [],
        $headers = []
    ) {
        return $this->request(
            $method,
            'event/subscription/' . $subscriptionName,
            $parameters,
            $headers
        );
    }

    public function request(
        $method,
        $url,
        $parameters = [],
        $headers = [] 
    ) {
        $request = $this->buildRequest(
            $method,
            $url,
            $parameters,
            $headers
        );
        return $this->getResponse($request);
    }

    public function buildRequest(
        $method,
        $url,
        $parameters = [],
        $headers = []
    ) {
        $fullHeaders = array_merge(
            $headers,
            $this->getDefaultHeaders()
        );

        if ($method === 'GET' || $method === 'PUT') {
            if (is_array($parameters))
                $query = http_build_query($parameters);
            else
                $query = $parameters;

            $uri = new Uri($url);
            $fullUri = $uri->withQuery($query);

            return new HttpRequest(
                $method,
                $fullUri,
                $fullHeaders
            );
        } else {
            return new HttpRequest(
                $method,
                $url,
                $fullHeaders,
                http_build_query($parameters)
            );
        }
    }

    public function refreshRequest($request)
    {
        return $this->buildRequest(
            $request->getMethod(),
            $request->getUri(),
            $request->getUri()->getQuery()
        );
    }

    public function getResponse($request)
    {
        try {
            $response = $this->httpClient->send($request);
            $responseBody = $response->getBody()->getContents();
            return json_decode($responseBody);
        }
        catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() == 401) {
                $this->refreshSession();
                $this->getResponse(
                    $this->refreshRequest($request)
                );
            }
            else
                throw $e;
        }
    }

    private function getDefaultHeaders()
    {
        return [ 'BhRestToken' => $this->authClient->getRestToken() ];
    }
}
