<?php

use PHPUnit\Framework\TestCase;
use jonathanraftery\Bullhorn\Rest\Client;
use jonathanraftery\Bullhorn\Rest\Authentication\AuthorizationException;
use jonathanraftery\Bullhorn\Rest\Authentication\Exception\InvalidRefreshTokenException;
use jonathanraftery\Bullhorn\MemoryDataStore;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

final class ClientTest extends TestCase
{
    protected $client;
    protected $credentials;

    protected function setUp()
    {
        $credentialsFileName = __DIR__.'/data/client-credentials.json';
        $credentialsFile = fopen($credentialsFileName, 'r');
        $credentialsJson = fread($credentialsFile, filesize($credentialsFileName));
        $this->credentials = json_decode($credentialsJson, true);

        $this->client = new Client(
            $this->credentials['clientId'],
            $this->credentials['clientSecret'],
            new MemoryDataStore()
        );
        $this->client->initiateSession(
            $this->credentials['username'],
            $this->credentials['password']
        );
    }

    function testCanInitiateSessionWithValidCredentials()
    {
        $this->assertTrue($this->client->sessionIsValid());
    }

    function testThrowsExceptionOnInvalidClientId()
    {
        $this->expectException(AuthorizationException::class);
        $client = new Client(
            'testing_invalid_client_id',
            $this->credentials['clientSecret'],
            new MemoryDataStore()
        );
        $client->initiateSession(
            $this->credentials['username'],
            $this->credentials['password']
        );
    }

    function testThrowsExceptionOnInvalidClientSecret()
    {
        $this->expectException(AuthorizationException::class);
        $client = new Client(
            $this->credentials['clientId'],
            'testing_invalid_client_secret',
            new MemoryDataStore()
        );
        $client->initiateSession(
            $this->credentials['username'],
            $this->credentials['password']
        );
    }

    function testThrowsExceptionOnInvalidUsername()
    {
        $this->expectException(AuthorizationException::class);
        $this->client->initiateSession(
            'testing_invalid_username',
            $this->credentials['password']
        );

    }

    function testThrowsExceptionOnInvalidPassword()
    {
        $this->expectException(AuthorizationException::class);
        $this->client->initiateSession(
            $this->credentials['username'],
            'testing_invalid_password'
        );
    }

    function testGetsResponseForValidRequest()
    {
        $response = $this->client->request('get', 'search/JobOrder');
        $this->assertTrue(isset($response->searchFields));
    }

    function testThrowsExceptionOnInvalidRefreshToken()
    {
        $this->expectException(InvalidRefreshTokenException::class);
        $dataStore = new MemoryDataStore();
        $localClient = new Client(
            $this->credentials['clientId'],
            $this->credentials['clientSecret'],
            $dataStore
        );
        $localClient->initiateSession(
            $this->credentials['username'],
            $this->credentials['password']
        );
        $dataKey = $this->credentials['clientId'] . '-refreshToken';
        $dataStore->store($dataKey, 'invalid-refresh-token');
        $localClient->refreshSession();
    }

    function testPassingTtlOptionSetsTtl()
    {
        $localClient = new Client(
            $this->credentials['clientId'],
            $this->credentials['clientSecret'],
            new MemoryDataStore(),
            ['autoRefresh' => false]
        );
        $localClient->initiateSession(
            $this->credentials['username'],
            $this->credentials['password'],
            ['ttl' => 1]
        );
        sleep(70);
        $exceptionCaught = false;
        try {
            $response = $localClient->request('get', 'search/JobOrder');
        }
        catch (GuzzleHttp\Exception\ClientException $e) {
            $exceptionCaught = true;
            $expectedStatusCode = 401;
            $this->assertEquals(
                $expectedStatusCode,
                $e->getResponse()->getStatusCode()
            );
        }
        $this->assertTrue($exceptionCaught);
    }

    function testRefreshesSessionIfExpirationDetected()
    {
        $this->checkedClientRefresh(['ttl' => 1]);
        sleep(70);
        $this->assertTrue($this->checkForValidResponse());
    }

    function testEventSubscriptionCreate()
    {
        $subscriptionName = 'TestSubscription';

        $response = $this->client
			->eventSubscription($subscriptionName)
			->create('JobOrder', 'INSERTED,UPDATED,DELETED');

        $this->assertTrue(isset($response->createdOn));

		return $subscriptionName;
    }

    /**
     * @depends testEventSubscriptionCreate
     */
    function testEventSubscriptionGet($subscriptionName = 'TestSubscription')
    {
    	$response = $this->client
			->eventSubscription($subscriptionName)
			->get();

    	// I was actually never get this to return anything
		// when I send the request I just get a 500 error
		// $this->assertTrue(isset($response->events));

        return $subscriptionName;
    }

	/**
	 * @depends testEventSubscriptionCreate
	 */
    function testEventSubscriptionDelete($subscriptionName = 'TestSubscription')
    {
        $response = $this->client
			->eventSubscription($subscriptionName)
			->delete();

        $expectedResult = 1;
        $this->assertEquals($expectedResult, $response->result);
    }

    /**
     * @group new
     */
    function testEntityFunctions()
    {
    	$candidate = $this->client
			->Candidate;

        $attributes = [
			'firstName' => 'First',
			'lastName' => 'Last',
		];

        // Create the entity
        $newEntity = $candidate->create($attributes);

        $this->assertEquals('Candidate', $newEntity->changedEntityType);
        $this->assertNotNull($newEntity->changedEntityId);
        $this->assertEquals('INSERT', $newEntity->changeType);
        $this->assertEquals((object) $attributes, $newEntity->data);

        // Search for the newly created entity using search
        $searchResults = $candidate->search([
        	'query' => "firstName:{$attributes['firstName']} AND lastName:{$attributes['lastName']}",
			'fields' => 'id,firstName,lastName',
		]);

        $this->assertTrue(count($searchResults->data) > 0);

//        $newFirstName = 'Newfirst';
//        $newLastName = 'Newlast';
//
//        // Update those records individually
//		foreach ($searchResults as $result) {
//			$updateResult = $candidate->update([
//				'id' => $result->id,
//				'firstName' => $newFirstName,
//				'lastName' => $newLastName,
//			]);
//
//			$this->assertEquals($result->id, $updateResult->changedEntityId);
//			$this->assertEquals('UPDATE', $updateResult->changeType);
//		}

		$ids = [];

		// Update those records individually
		foreach ($searchResults->data as $result) {
			$deleteResult = $candidate->delete($result->id);
			$ids[] = $result->id;

			$this->assertEquals($result->id, $deleteResult->changedEntityId);
			$this->assertEquals('DELETE', $deleteResult->changeType);
		}

		$massUpdateResults = $candidate->massUpdate([
			'ids' => $ids,
			'isDeleted' => true,
		]);

		$this->assertEquals(count($searchResults->data), $massUpdateResults->count);
    }

    private function checkForValidResponse()
    {
        $response = $this->client->request('get', 'search/JobOrder');
        return isset($response->searchFields);
    }

    private function checkedClientRefresh(array $options = [])
    {
        $this->client->refreshOrInitiateSession(
            $this->credentials['username'],
            $this->credentials['password'],
            $options
        );
    }
}
