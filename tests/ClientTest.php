<?php

use PHPUnit\Framework\TestCase;
use jonathanraftery\Bullhorn\REST\Client as Client;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

final class ClientTest extends TestCase
{
    /**
     * @dataProvider validCredentialsProvider
     */
    function testCanBeConstructedFromValidCredentials(
        $clientId,
        $clientSecret,
        $username,
        $password
    ) {
        try {
            $client = new Client(
                $clientId, $clientSecret, $username, $password
            );
        } catch (Exception $e) {
            $this->fail();
        }

        $this->assertTrue(TRUE);
    }

    /**
     * @dataProvider invalidClientIdCredentialsProvider
     */
    function testThrowsExceptionOnInvalidClientId(
        $clientId,
        $clientSecret,
        $username,
        $password
    ) {
        $this->expectException(\InvalidArgumentException::class);
        $client = new Client(
            $clientId, $clientSecret, $username, $password
        );
    }
    /**
     * @dataProvider invalidClientSecretCredentialsProvider
     */
    function testThrowsExceptionOnInvalidClientSecret(
        $clientId,
        $clientSecret,
        $username,
        $password
    ) {
        $this->expectException(IdentityProviderException::class);
        $client = new Client(
            $clientId, $clientSecret, $username, $password
        );
    }
    /**
     * @dataProvider invalidUsernameCredentialsProvider
     */
    function testThrowsExceptionOnInvalidUsername(
        $clientId,
        $clientSecret,
        $username,
        $password
    ) {
        $this->expectException(\InvalidArgumentException::class);
        $client = new Client(
            $clientId, $clientSecret, $username, $password
        );
    }
    /**
     * @dataProvider invalidPasswordCredentialsProvider
     */
    function testThrowsExceptionOnInvalidPassword(
        $clientId,
        $clientSecret,
        $username,
        $password
    ) {
        $this->expectException(\InvalidArgumentException::class);
        $client = new Client(
            $clientId, $clientSecret, $username, $password
        );
    }

    /**
     * @dataProvider validCredentialsProvider
     */
    function testGetsResponseForValidRequest(
        $clientId,
        $clientSecret,
        $username,
        $password
    ) {
        $client = new Client(
            $clientId, $clientSecret, $username, $password
        );
        $response = $client->get('search/JobOrder', []);
        $this->assertTrue(isset($response->searchFields));
    }

    function validCredentialsProvider()
    {
        $credentialsFileName = __DIR__.'/data/client-credentials.json';
        $credentialsFile = fopen($credentialsFileName, 'r');
        $credentialsJson = fread($credentialsFile, filesize($credentialsFileName));
        $credentials = json_decode($credentialsJson);
        return [
            'valid credentials' => [
                $credentials->clientId,
                $credentials->clientSecret,
                $credentials->username,
                $credentials->password
            ]
        ];
    }

    function invalidClientIdCredentialsProvider()
    {
        $credentialsFileName = __DIR__.'/data/client-credentials.json';
        $credentialsFile = fopen($credentialsFileName, 'r');
        $credentialsJson = fread($credentialsFile, filesize($credentialsFileName));
        $credentials = json_decode($credentialsJson);
        return [
            'invalid credentials (client ID)' => [
                'testing_invalid_client_id',
                $credentials->clientSecret,
                $credentials->username,
                $credentials->password
            ]
        ];
    }
    function invalidClientSecretCredentialsProvider()
    {
        $credentialsFileName = __DIR__.'/data/client-credentials.json';
        $credentialsFile = fopen($credentialsFileName, 'r');
        $credentialsJson = fread($credentialsFile, filesize($credentialsFileName));
        $credentials = json_decode($credentialsJson);
        return [
            'invalid credentials (client ID)' => [
                $credentials->clientId,
                'testing_invalid_client_secret',
                $credentials->username,
                $credentials->password
            ]
        ];
    }
    function invalidUsernameCredentialsProvider()
    {
        $credentialsFileName = __DIR__.'/data/client-credentials.json';
        $credentialsFile = fopen($credentialsFileName, 'r');
        $credentialsJson = fread($credentialsFile, filesize($credentialsFileName));
        $credentials = json_decode($credentialsJson);
        return [
            'invalid credentials (username)' => [
                $credentials->clientId,
                $credentials->clientSecret,
                'testing_invalid_username',
                $credentials->password
            ]
        ];
    }
    function invalidPasswordCredentialsProvider()
    {
        $credentialsFileName = __DIR__.'/data/client-credentials.json';
        $credentialsFile = fopen($credentialsFileName, 'r');
        $credentialsJson = fread($credentialsFile, filesize($credentialsFileName));
        $credentials = json_decode($credentialsJson);
        return [
            'invalid credentials (password)' => [
                $credentials->clientId,
                $credentials->clientSecret,
                $credentials->username,
                'testing_invalid_password'
            ]
        ];
    }
}
