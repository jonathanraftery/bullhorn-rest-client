<?php


namespace jonathanraftery\Bullhorn\Rest;

use jonathanraftery\Bullhorn\Rest\Exceptions\MissingIdException;

class Entity
{
	const MAX_ENTITY_REQUEST_COUNT = 500;

	protected $client;
	protected $entityName;

	/**
	 * Entity constructor.
	 *
	 * @param Client $client
	 * @param string $entityName
	 */
	public function __construct(Client $client, string $entityName)
	{
		$this->client = $client;
		$this->entityName = $entityName;
	}

	/**
	 * Performs a search call for the entity
	 *
	 * @param array $options
	 * @return array
	 * @throws Authentication\Exception\InvalidRefreshTokenException
	 */
	public function search(array $options)
	{
		$this->prepareOptions($options);

		return $this->client->request(
			'GET',
			"search/{$this->entityName}",
			['query' => $options]
		);
	}

	/**
	 * Performs a query request against the given entity
	 *
	 * @param $options
	 * @return mixed
	 * @throws Authentication\Exception\InvalidRefreshTokenException
	 */
	public function query($options)
	{
		$this->prepareOptions($options);

		return $this->client->request(
			'GET',
			"query/{$this->entityName}",
			['query' => $options]
		);
	}

	/**
	 * Gets the meta for an entity
	 *
	 * @param array $fields
	 * @param string $meta
	 * @return mixed
	 * @throws Authentication\Exception\InvalidRefreshTokenException
	 */
	public function meta(array $fields = ['*'], string $meta = 'full')
	{
		return $this->client->request(
			'GET',
			"meta/{$this->entityName}",
			['query' => [
				'fields' => implode(',', $fields),
				'meta' => $meta,
			]]
		);
	}

	/**
	 * Creates a new entity
	 *
	 * @param array $attributes
	 * @return mixed
	 * @throws Authentication\Exception\InvalidRefreshTokenException
	 */
	public function create(array $attributes)
	{
		// Would be ideal to check the meta for the entity
		// and do some validation prior to sending the request
		// as Bullhorn's response doesn't indicate which fields
		// are missing
//		$meta = $this->meta();
//		$required = [];
//
//		foreach ($meta->fields as $field) {
//			if (isset($field->required) && $field->required) {
//				$required[] = $field;
//			}
//		}
//
//		print_r($required);

		return $this->client->request(
			'PUT',
			"entity/{$this->entityName}",
			['json' => $attributes]
		);
	}

	/**
	 * Updates an entity's attributes
	 *
	 * @param array $attributes
	 * @return mixed
	 * @throws MissingIdException
	 * @throws Authentication\Exception\InvalidRefreshTokenException
	 */
	public function update(array $attributes)
	{
		if (!isset($attributes['id'])) {
			throw new MissingIdException("Cannot update when missing ID attribute for {$this->entityName}.");
		}

		return $this->client->request(
			'POST',
			"entity/{$this->entityName}",
			['json' => $attributes]
		);
	}

	/**
	 * Performs a mass update on the given ids
	 *
	 * @param array $attributes
	 * @return mixed
	 * @throws MissingIdException
	 * @throws Authentication\Exception\InvalidRefreshTokenException
	 */
	public function massUpdate(array $attributes)
	{
		if (!isset($attributes['ids'])) {
			throw new MissingIdException('Missing `ids` parameter. Cannot update.');
		}

		return $this->client->request(
			'POST',
			"massUpdate/$this->entityName",
			['json' => $attributes]
		);
	}

	/**
	 * Gets the properties that are capable of being mass updated
	 *
	 * @return mixed
	 * @throws Authentication\Exception\InvalidRefreshTokenException
	 */
	public function getMassUpdateProperties()
	{
		return $this->client->request(
			'GET',
			"massUpdate/$this->entityName"
		);
	}

	/**
	 * Deletes an entity
	 *
	 * @param null $id
	 * @return mixed
	 * @throws MissingIdException
	 * @throws Authentication\Exception\InvalidRefreshTokenException
	 */
	public function delete($id = null)
	{
		if (!$id) {
			throw new MissingIdException("Cannot delete {$this->entityName} without specifying an ID.");
		}

		return $this->client->request(
			'DELETE',
			"entity/$this->entityName/{$id}"
		);
	}

	/**
	 * Gets all the ids based on a given condition
	 *
	 * @param $conditions
	 * @return mixed
	 * @throws Authentication\Exception\InvalidRefreshTokenException
	 */
	protected function getAllIdsWhere(string $conditions)
	{
		$conditions = urlencode($conditions);

		$response = $this->client->request(
			'GET',
			"search/{$this->entityName}?query={$conditions}"
		);

		return $response->data;
	}

	/**
	 * Gets all the entities by the given ids
	 *
	 * @param array $ids
	 * @param array $fields
	 * @return array
	 * @throws \Throwable
	 */
	public function getAllById(array $ids, array $fields = ['*'])
	{
		$jobsPerRequest = self::MAX_ENTITY_REQUEST_COUNT;
		$chunkedIds = array_chunk($ids, $jobsPerRequest);
		$requests = [];

		foreach ($chunkedIds as $ids) {
			$conditions = '';

			foreach ($ids as $id) {
				$conditions .= "id:$id OR ";
			}

			$conditions = substr($conditions, 0, -4);

			$requestParameters = [
				'query' => $conditions,
				'count' => $jobsPerRequest,
				'fields' => implode(',', $fields)
			];

			$requests[] = $this->client->buildRequest(
				'GET',
				"search/{$this->entityName}",
				$requestParameters
			);
		}

		$responses = $this->client->requestMultiple($requests);

		$entities = [];

		foreach ($responses as $response) {
			$data = json_decode($response->getBody()->getContents())->data;

			foreach ($data as $entity) {
				$entities[] = $entity;
			}
		}

		return $entities;
	}

	/**
	 * Prepares request options
	 *
	 * @param $options
	 */
	protected function prepareOptions(&$options)
	{
		if (!isset($options['fields'])) {
			$options['fields'] = 'id';
		}

		if (isset($options['fields']) && is_array($options['fields'])) {
			$options['fields'] = implode(',', $options['fields']);
		}
	}
}
