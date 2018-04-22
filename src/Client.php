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

    public function getJobOrdersModifiedSince($startDateTime, array $extraParameters = null)
    {
        $timestamp = $startDateTime->format('YmdHis');
        $conditions = "dateLastModified:[$timestamp TO *]";
        $jobOrders = $this->getJobOrdersWhere($conditions, $extraParameters);
        return $jobOrders;
    }

    public function getJobOrdersWhere($conditions, array $extraParameters = null)
    {
        $ids = $this->getAllJobOrderIdsWhere($conditions)->data;
        $jobOrders = $this->getJobOrdersById($ids, $extraParameters);
        return $jobOrders;
    }

    public function getAllJobOrderIdsWhere($conditions)
    {
        $response = $this->get(
            'search/JobOrder',
            ['query' => $conditions]
        );
        return $response;
    }

    public function getJobOrdersById(array $ids, array $extraParameters = null)
    {
        $jobsPerRequest = self::MAX_ENTITY_REQUEST_COUNT;
        $chunkedIds = array_chunk($ids, $jobsPerRequest);
        $requests = [];

        foreach ($chunkedIds as $ids) {
            $conditions = '';
            foreach ($ids as $id)
                $conditions .= "id:$id OR ";
            $conditions = substr($conditions, 0, -4);

            $requestParameters = array_merge($extraParameters, [
                'query' => $conditions,
                'count' => $jobsPerRequest
            ]);

            $requests[] = $this->buildRequest(
                'GET',
                'search/JobOrder',
                $requestParameters
            );
        }

        $promises = [];
        foreach ($requests as $request)
            $promises[] = $this->httpClient->sendAsync($request);
        $responses = Promise\unwrap($promises);

        $jobOrders = [];
        foreach ($responses as $response) {
            $data = json_decode($response->getBody()->getContents())->data;
            foreach ($data as $jobOrder)
                $jobOrders[] = $jobOrder;
        }

        return $jobOrders;
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
