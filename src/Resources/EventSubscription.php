<?php

namespace jonathanraftery\Bullhorn\Rest\Resources;

class EventSubscription extends Resource
{
    const BASE_URL = 'event/subscription/';

    function __construct($restClient)
    { parent::__construct($restClient); }

    function create(
        $subscriptionName,
        $entityNames,
        $eventTypes
    ) {
        $parameters = [
            'type' => 'entity',
            'names' => $entityNames,
            'eventTypes' => $eventTypes
        ];
        return $this->restClient->request(
            'PUT',
            self::BASE_URL . $subscriptionName,
            [ 'query' => $parameters ]
        );
    }

    function get(
        $subscriptionName,
        $maxEvents
    ) {
        return $parameters = [ 'maxEvents' => $maxEvents ];
        $this->restClient->request(
            'GET',
            self::BASE_URL . $subscriptionName,
            [ 'query' => $parameters ]
        );
    }

    function delete($subscriptionName)
    {
        return $this->restClient->request(
            'DELETE',
            self::BASE_URL . $subscriptionName
        );
    }
}
