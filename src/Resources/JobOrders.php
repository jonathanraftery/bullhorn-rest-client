<?php

namespace jonathanraftery\Bullhorn\Rest\Resources;

class JobOrders extends Resource
{
    const MAX_ENTITY_REQUEST_COUNT = 500;

    public function __construct($restClient)
    { parent::__construct($restClient); }

    public function search($conditions, $fields = ['id'])
    {
        $ids = $this->getAllIdsWhere($conditions);
        if (count($fields) === 1 && $fields[0] === 'id')
            return $ids;

        $jobOrders = $this->getAllById($ids, $fields);
        return $jobOrders;
    }

    private function getAllIdsWhere($conditions)
    {
        $conditions = urlencode($conditions);
        $response = $this->restClient->request(
            'GET',
            "search/JobOrder?query=$conditions"
        );
        return $response->data;
    }

    private function getAllById(array $ids, $fields = ['*'])
    {
        $jobsPerRequest = self::MAX_ENTITY_REQUEST_COUNT;
        $chunkedIds = array_chunk($ids, $jobsPerRequest);
        $requests = [];

        foreach ($chunkedIds as $ids) {
            $conditions = '';
            foreach ($ids as $id)
                $conditions .= "id:$id OR ";
            $conditions = substr($conditions, 0, -4);

            $requestParameters = [
                'query' => $conditions,
                'count' => $jobsPerRequest,
                'fields' => implode(',', $fields)
            ];

            $requests[] = $this->restClient->buildRequest(
                'GET',
                'search/JobOrder',
                $requestParameters
            );
        }

        $responses = $this->restClient->requestMultiple($requests);

        $jobOrders = [];
        foreach ($responses as $response) {
            $data = json_decode($response->getBody()->getContents())->data;
            foreach ($data as $jobOrder)
                $jobOrders[] = $jobOrder;
        }

        return $jobOrders;
    }
}
