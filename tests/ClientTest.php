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
        $response = $this->client->EventSubscription->create(
            $subscriptionName,
            'JobOrder',
            'INSERTED,UPDATED,DELETED'
        );
        $this->assertTrue(isset($response->createdOn));
        return $subscriptionName;
    }

    /**
     * @depends testEventSubscriptionCreate
     */
    function testEventSubscriptionGet($subscriptionName)
    {
        $this->assertTrue(true);
        return $subscriptionName;
    }

    /**
     * @depends testEventSubscriptionGet
     */
    function testEventSubscriptionDelete($subscriptionName)
    {
        $response = $this->client->EventSubscription->delete($subscriptionName);
        $expectedResult = 1;
        $this->assertEquals($response->result, $expectedResult);
    }

    /**
     * @group new
     */
    function testJobOrders()
    {
        $jobs = $this->client->JobOrders->search('isOpen:1 AND isPublic:1 AND isDeleted:0', ['*']);
        print(count($jobs));
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
