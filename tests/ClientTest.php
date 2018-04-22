<?php

use PHPUnit\Framework\TestCase;
use jonathanraftery\Bullhorn\Rest\Client;
use jonathanraftery\Bullhorn\Rest\Authentication\AuthorizationException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

final class ClientTest extends TestCase
{
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
            $credentials['password']
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
            $credentials['password']
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
            $credentials['password']
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
            $credentials['password']
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
            'testing_invalid_password'
        );
    }

    /**
     * @dataProvider credentialsProvider
     */
    function testGetsResponseForValidRequest($credentials)
    {
        $client = new Client(
            $credentials['clientId'],
            $credentials['clientSecret'],
            $credentials['username'],
            $credentials['password']
        );
        $response = $client->get('search/JobOrder', []);
        $this->assertTrue(isset($response->searchFields));
    }

    /**
     * @depends testCanBeConstructedFromValidCredentials
     */
    function testGetsAllJobIdsForValidJobOrderIdSearch($client)
    {
        $response = $client->getAllJobOrderIdsWhere(
            'isOpen:1 AND isPublic:1 AND isDeleted:0' 
        );
        $this->assertEquals($response->total, count($response->data));
    }

    /**
     * @depends testCanBeConstructedFromValidCredentials
     */
    function testGetsAllJobsWhenMoreThanMaxReturned($client)
    {
        $response = $client->get(
            'search/JobOrder',
            ['query' => 'isOpen:1']
        );
        $jobOrders = $client->getJobOrdersWhere(
            'isOpen:1',
            ['id', 'title', 'isOpen']
        );
        $this->assertEquals(count($jobOrders), $response->total);
        return $jobOrders;
    }

    /**
     * @depends testGetsAllJobsWhenMoreThanMaxReturned
     */
    function testNoDuplicateJobsReturnedForJobQuery($jobOrders)
    {
        $seen = [];
        $duplicateFound = false;
        foreach ($jobOrders as $jobOrder) {
            if (in_array($jobOrder->id, $seen)) {
                $duplicateFound = true;
                break;
            }
            else
                $seen[] = $jobOrder->id;
        }
        $this->assertFalse($duplicateFound);
    }

    /**
     * @depends testCanBeConstructedFromValidCredentials
     */
    function testBuildsPutBodyCorrectly($client)
    {
        $parameters = [
            'query' => 'isOpen:1 AND isPublic:1 AND isDeleted:0'
        ];
        $request = $client->buildRequest(
            'PUT',
            'search/JobOrder',
            $parameters
        );
        $expectedBody = http_build_query($parameters);
        $this->assertEquals($request->getBody()->getContents(), $expectedBody);
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
