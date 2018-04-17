<?php

namespace jonathanraftery\Bullhorn\REST;
use jonathanraftery\Bullhorn\REST\Authentication\Client as AuthClient;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request as HttpRequest;

class Client
{
    private $authClient;
    private $session;
    private $httpClient;

    public function __construct($clientId, $clientSecret, $username, $password)
    {
        $this->authClient = new AuthClient(
            $clientId, $clientSecret, $username, $password
        );
        $this->session = $this->authClient->createSession();
        $this->httpClient = new HttpClient([
            'base_uri' => $this->session->restUrl
        ]);
    }

    public function get($url, $parameters)
    {
        $request = $this->buildRequest($url, $parameters);
        $response = $this->httpClient->send($request);
        return json_decode($response->getBody()->getContents());
    }

    private function getDefaultHeaders()
    {
        return [
            'BhRestToken' => $this->session->BhRestToken
        ];
    }

    private function buildRequest($url, $parameters, $method = 'GET', $headers = [])
    {
        $defaultHeaders = [
            'BhRestToken' => $this->session->BhRestToken
        ];
        $headers = array_merge($defaultHeaders, $headers);
        return new HttpRequest(
            $method,
            $url,
            $headers
        );
    }
}
