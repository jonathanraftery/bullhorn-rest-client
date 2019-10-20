<?php

namespace jonathanraftery\Bullhorn\Rest;

use jonathanraftery\Bullhorn\Rest\Authentication\Exception\InvalidRefreshTokenException;

class EventSubscription
{
    protected $client;
    protected $url;

	/**
	 * EventSubscription constructor
	 *
	 * @param Client $client
	 * @param string $subscriptionName
	 */
	public function __construct(Client $client, string $subscriptionName)
	{
		$this->client = $client;
		$this->url = "event/subscription/${subscriptionName}";
    }

	/**
	 * Creates an event subscription
	 *
	 * @param array|string $entityNames
	 * @param array|string $eventTypes
	 * @return mixed
	 * @throws InvalidRefreshTokenException
	 */
    public function create($entityNames, $eventTypes)
	{
        return $this->client->request('PUT', $this->url, [
        	'query' => [
				'type' => 'entity',
				'names' => is_array($entityNames)
					? implode(',', $entityNames)
					: $entityNames,
				'eventTypes' => is_array($eventTypes)
					? implode(',', $eventTypes)
					: $eventTypes,
			]
		]);
    }

	/**
	 * Gets an event subscription events
	 *
	 * @param int $maxEvents
	 * @return mixed
	 * @throws InvalidRefreshTokenException
	 */
    public function get(int $maxEvents = 100)
	{
        return $this->client->request('GET', $this->url, [
        	'query' => ['maxEvents' => $maxEvents]
		]);
    }

	/**
	 * Deletes a subscription
	 *
	 * @return mixed
	 * @throws InvalidRefreshTokenException
	 */
    public function delete()
    {
        return $this->client->request('DELETE', $this->url);
    }
}
