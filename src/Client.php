<?php

namespace jonathanraftery\Bullhorn\Rest;
use jonathanraftery\Bullhorn\Rest\Authentication\Client as AuthClient;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request as HttpRequest;
use GuzzleHttp\Psr7\Uri;

class Client
{
    private $authClient;
    private $httpClient;

    public function __construct(
        $clientId,
        $clientSecret,
        $username,
        $password
    ) {
        $this->authClient = new AuthClient(
            $clientId,
            $clientSecret
        );
        $this->authClient->initiateSession(
            $username,
            $password
        );
        $this->httpClient = new HttpClient([
            'base_uri' => $this->authClient->getRestUrl()
        ]);
    }

    public function sessionIsValid()
    { return $this->authClient->sessionIsValid(); }

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

    public function get($url, $parameters = [], $headers = [])
    {
        return $this->request(
            'GET',
            $url,
            $parameters,
            $headers
        );
    }

    public function post($url, $parameters = [], $headers = [])
    {
        return $this->request(
            'POST',
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

        if ($method === 'GET') {
            $query = http_build_query($parameters);
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

    private function getDefaultHeaders()
    {
        return [
            'BhRestToken' => $this->authClient->getRestToken()
        ];
    }
}
