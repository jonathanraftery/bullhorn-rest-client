<?php

use PHPUnit\Framework\TestCase;
use jonathanraftery\Bullhorn\Rest\Client;
use jonathanraftery\Bullhorn\Rest\Authentication\AuthorizationException;
use jonathanraftery\Bullhorn\Rest\Authentication\Exception\InvalidRefreshTokenException;
use jonathanraftery\Bullhorn\MemoryDataStore;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

final class ClientTest extends TestCase
{
    /**
     * @group new
     */
    function testCanBeConstructedFromValidCredentials()
    {
        $credentialsFileName = __DIR__.'/data/client-credentials.json';
        $credentialsFile = fopen($credentialsFileName, 'r');
        $credentialsJson = fread($credentialsFile, filesize($credentialsFileName));
        $credentials = json_decode($credentialsJson, true);

        $client = new Client(
            $credentials['clientId'],
            $credentials['clientSecret'],
            $credentials['username'],
            $credentials['password'],
            new MemoryDataStore()
        );

        $this->assertTrue($client->sessionIsValid());
        return $client;
    }

    /**
     * @dataProvider credentialsProvider
     */
    function testThrowsExceptionOnInvalidClientId($credentials)
    {
        $this->expectException(AuthorizationException::class);
        $client = new Client(
            'testing_invalid_client_id',
            $credentials['clientSecret'],
            $credentials['username'],
            $credentials['password'],
            new MemoryDataStore()
        );
    }

    /**
     * @dataProvider credentialsProvider
     */
    function testThrowsExceptionOnInvalidClientSecret($credentials)
    {
        $this->expectException(AuthorizationException::class);
        $client = new Client(
            $credentials['clientId'],
            'testing_invalid_client_secret',
            $credentials['username'],
            $credentials['password'],
            new MemoryDataStore()
        );
    }
    /**
     * @dataProvider credentialsProvider
     */
    function testThrowsExceptionOnInvalidUsername($credentials)
    {
        $this->expectException(AuthorizationException::class);
        $client = new Client(
            $credentials['clientId'],
            $credentials['clientSecret'],
            'testing_invalid_username',
            $credentials['password'],
            new MemoryDataStore()
        );
    }
    /**
     * @dataProvider credentialsProvider
     */
    function testThrowsExceptionOnInvalidPassword($credentials)
    {
        $this->expectException(AuthorizationException::class);
        $client = new Client(
            $credentials['clientId'],
            $credentials['clientSecret'],
            $credentials['username'],
            'testing_invalid_password',
            new MemoryDataStore()
        );
    }

    /**
     * @depends testCanBeConstructedFromValidCredentials
     */
    function testGetsResponseForValidRequest($client)
    {
        $response = $client->request('get', 'search/JobOrder', []);
        $this->assertTrue(isset($response->searchFields));
    }

    /**
     * @depends testCanBeConstructedFromValidCredentials
     */
    function testBuildsPostBodyCorrectly($client)
    {
        $parameters = [
            'query' => 'isOpen:1 AND isPublic:1 AND isDeleted:0'
        ];
        $request = $client->buildRequest(
            'POST',
            'search/JobOrder',
            $parameters
        );
        $expectedBody = http_build_query($parameters);
        $this->assertEquals($request->getBody()->getContents(), $expectedBody);
    }

    /**
     * @dataProvider credentialsProvider
     */
    function testThrowsExceptionOnInvalidRefreshToken($credentials)
    {
        $this->expectException(InvalidRefreshTokenException::class);
        $dataStore = new MemoryDataStore();
        $client = new Client(
            $credentials['clientId'],
            $credentials['clientSecret'],
            $credentials['username'],
            $credentials['password'],
            $dataStore
        );
        $dataKey = $credentials['clientId'] . '-refreshToken';
        $dataStore->store($dataKey, 'invalid-refresh-token');
        $client->refreshSession();
    }

    /**
     * @depends testCanBeConstructedFromValidCredentials
     */
    function testRefreshesSessionCorrectly($client)
    {
        $this->checkedClientRefresh($client);
        $dummyRequest = $client->buildRequest('get', 'search/JobOrder', []);
        $firstRestToken = $dummyRequest->getHeader('BhRestToken')[0];
        $this->checkedClientRefresh($client);
        $dummyRequest = $client->buildRequest('get', 'search/JobOrder', []);
        $secondRestToken = $dummyRequest->getHeader('BhRestToken')[0];
        $this->assertNotEquals($firstRestToken, $secondRestToken);
        $this->assertFalse(empty($firstRestToken) || empty($secondRestToken));
    }

    /**
     * @depends testCanBeConstructedFromValidCredentials
     */
    function testRefreshesSessionIfExpirationDetected($client)
    {
        $this->checkedClientRefresh($client, ['ttl' => 1]);
        $dummyRequest = $client->buildRequest('get', 'search/JobOrder', []);
        $firstRestToken = $dummyRequest->getHeader('BhRestToken')[0];
        sleep(70);
        $response = $client->request('get', 'search/JobOrder', []);
        print_r($response);
        $dummyRequest = $client->buildRequest('get', 'search/JobOrder', []);
        $secondRestToken = $dummyRequest->getHeader('BhRestToken')[0];
        $this->assertNotEquals($firstRestToken, $secondRestToken);
        $this->assertFalse(empty($firstRestToken) || empty($secondRestToken));
    }

    /**
     * @depends testCanBeConstructedFromValidCredentials
     */
    function testGetsValidResponseForSearch($client)
    {
        $response = $client->search('JobOrder');
        $this->assertTrue(isset($response->searchFields));
    }

    /**
     * @depends testCanBeConstructedFromValidCredentials
     * @group new
     */
    function testCreateEventSubscription($client)
    {
        $testSubscriptionName = 'TestUpdatedJobOrders';
        $response = $client->eventSubscription(
            'PUT',
            $testSubscriptionName,
            [
                'type' => 'entity',
                'names' => 'JobOrder',
                'eventTypes' => 'INSERTED,UPDATED,DELETED'
            ]
        );
        $this->assertTrue(isset($response->createdOn));
        return $testSubscriptionName;
    }

    /**
     * @depends testCanBeConstructedFromValidCredentials
     * @depends testCreateEventSubscription
     * @group new
     */
    function testGetEvents($client, $testSubscriptionName)
    {
        $response = $client->eventSubscription(
            'GET',
            $testSubscriptionName,
            ['maxEvents' => 10]
        );
        print_r($response);
        $this->assertTrue(isset($response->requestId));
    }

    /**
     * @depends testCanBeConstructedFromValidCredentials
     * @depends testCreateEventSubscription
     * @group new
     */
    function testDeleteEventSubscription($client, $testSubscriptionName)
    {
        $response = $client->eventSubscription(
            'DELETE',
            $testSubscriptionName
        );
        $this->assertTrue(isset($response->result));
    }

    function checkedClientRefresh($client, array $options = [])
    {
        try {
            $client->refreshSession($options);
        }
        catch (InvalidRefreshTokenException $e) {
            $credentials = $this->credentialsProvider();
            $client->initiateSession(
                $credentials[0][0]['username'],
                $credentials[0][0]['password'],
                $options
            );
        }
    }

    function credentialsProvider()
    {
        $credentialsFileName = __DIR__.'/data/client-credentials.json';
        $credentialsFile = fopen($credentialsFileName, 'r');
        $credentialsJson = fread($credentialsFile, filesize($credentialsFileName));
        $credentials = json_decode($credentialsJson, true);
        return [[$credentials]];
    }
}
