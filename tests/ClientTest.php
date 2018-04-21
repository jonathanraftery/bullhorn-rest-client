<?php

use PHPUnit\Framework\TestCase;
use jonathanraftery\Bullhorn\Rest\Client;
use jonathanraftery\Bullhorn\Rest\Authentication\AuthorizationException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

final class ClientTest extends TestCase
{
    /**
     * @dataProvider credentialsProvider
     */
    function testCanBeConstructedFromValidCredentials($credentials)
    {
        try {
            $client = new Client(
                $credentials['clientId'],
                $credentials['clientSecret'],
                $credentials['username'],
                $credentials['password']
            );
        } catch (Exception $e) {
            $this->fail();
        }

        $this->assertTrue(TRUE);
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
     * @dataProvider credentialsProvider
     */
    function testGetsResponseForValidJobOrderIdSearch($credentials)
    {
        $client = new Client(
            $credentials['clientId'],
            $credentials['clientSecret'],
            $credentials['username'],
            $credentials['password']
        );
        $response = $client->getJobOrdersWhere(
            'isOpen:1 AND isPublic:1 AND isDeleted:0' 
        );
        $this->assertTrue(isset($response->total));
    }

    /**
     * @dataProvider credentialsProvider
     */
    function testGetsAllJobIdsForValidJobOrderIdSearch($credentials)
    {
        $client = new Client(
            $credentials['clientId'],
            $credentials['clientSecret'],
            $credentials['username'],
            $credentials['password']
        );
        $response = $client->getAllJobOrderIdsWhere(
            'isOpen:1 AND isPublic:1 AND isDeleted:0' 
        );
        $this->assertEquals($response->total, count($response->data));
    }

    function credentialsProvider()
    {
        $credentialsFileName = __DIR__.'/data/client-credentials.json';
        $credentialsFile = fopen($credentialsFileName, 'r');
        $credentialsJson = fread($credentialsFile, filesize($credentialsFileName));
        $credentials = json_decode($credentialsJson, true);
        return [
            'valid credentials' => [$credentials]
        ];
    }
}
