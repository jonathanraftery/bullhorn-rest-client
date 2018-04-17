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

    public function __construct(
        $clientId,
        $clientSecret,
        $username,
        $password
    ) {
        $this->authClient = new AuthClient(
            $clientId,
            $clientSecret,
            $username,
            $password
        );
        $this->session = $this->authClient->createSession();
        $this->httpClient = new HttpClient([
            'base_uri' => $this->session->restUrl
        ]);
    }

    public function getJobOrdersWhere($conditions, $fields = ['id'])
    {
        $jobOrders = $this->get(
            'search/JobOrder',
            [
                'query' => $conditions,
                'fields' => implode(',', $fields)
            ]
        );
        return $jobOrders;
    }

    public function getAllJobOrderIdsWhere($conditions)
    {
        return $this->get(
            'search/JobOrder',
            [ 'query' => $conditions ]
        );
    }

    public function get(
        $url,
        $parameters,
        $headers = []
    ) {
        return $this->request(
            'GET',
            $url,
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

    public function getResponse($request)
    {
        $response = $this->httpClient->send($request);
        $responseBody = $response->getBody()->getContents();
        return json_decode($responseBody);
    }

    private function getDefaultHeaders()
    {
        return [
            'BhRestToken' => $this->session->BhRestToken
        ];
    }

    private function buildRequest(
        $method,
        $url,
        $parameters,
        $headers
    ) {
        $uri = new Uri($url);
        $query = http_build_query($parameters);
        $fullUri = $uri ->withQuery($query);
        $fullHeaders = array_merge(
            $headers,
            $this->getDefaultHeaders()
        );

        return new HttpRequest(
            $method,
            $fullUri,
            $fullHeaders
        );
    }
}
