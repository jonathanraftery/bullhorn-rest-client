<?php

namespace jonathanraftery\Bullhorn\REST;
use jonathanraftery\Bullhorn\REST\Authentication\Client as AuthClient;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request as HttpRequest;
use GuzzleHttp\Psr7\Uri;

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

    public function get($url, $parameters, $headers = [])
    {
        $request = $this->buildRequest($url, $parameters, 'GET', $headers);
        $response = $this->httpClient->send($request);
        return json_decode($response->getBody()->getContents());
    }

    private function getDefaultHeaders()
    {
        return [
            'BhRestToken' => $this->session->BhRestToken
        ];
    }

    private function buildRequest($url, $parameters, $method, $headers)
    {
        $uri = new Uri($url);
        return new HttpRequest(
            $method,
            $uri->withQuery(http_build_query($parameters)),
            array_merge($headers, $this->getDefaultHeaders())
        );
    }
}
