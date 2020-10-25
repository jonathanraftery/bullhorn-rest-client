<?php

use jonathanraftery\Bullhorn\Rest\Authentication\InvalidRefreshTokenException;
use jonathanraftery\Bullhorn\Rest\Authentication\MemoryDataStore;
use PHPUnit\Framework\TestCase;
use jonathanraftery\Bullhorn\Rest\Client;
use jonathanraftery\Bullhorn\Rest\Authentication\BullhornAuthException;

final class ClientTest extends TestCase {
    protected $client;

    protected function setUp(): void {
        $this->client = new Client(
            $_ENV[TestEnvKeys::BullhornClientId],
            $_ENV[TestEnvKeys::BullhornClientSecret],
            new MemoryDataStore()
        );
        $this->client->initiateSession(
            $_ENV[TestEnvKeys::BullhornUsername],
            $_ENV[TestEnvKeys::BullhornPassword]
        );
    }

    function testCanInitiateSessionWithValidCredentials()
    {
        $this->assertTrue($this->client->sessionIsValid());
    }

    function testThrowsExceptionOnInvalidClientId()
    {
        $this->expectException(BullhornAuthException::class);
        $client = new Client(
            'testing_invalid_client_id',
            $_ENV[TestEnvKeys::BullhornClientSecret],
            new MemoryDataStore()
        );
        $client->initiateSession(
            $_ENV[TestEnvKeys::BullhornUsername],
            $_ENV[TestEnvKeys::BullhornPassword]
        );
    }

    function testThrowsExceptionOnInvalidClientSecret()
    {
        $this->expectException(BullhornAuthException::class);
        $client = new Client(
            $_ENV[TestEnvKeys::BullhornClientId],
            'testing_invalid_client_secret',
            new MemoryDataStore()
        );
        $client->initiateSession(
            $_ENV[TestEnvKeys::BullhornUsername],
            $_ENV[TestEnvKeys::BullhornPassword]
        );
    }

    function testThrowsExceptionOnInvalidUsername()
    {
        $this->expectException(BullhornAuthException::class);
        $this->client->initiateSession(
            'testing_invalid_username',
            $_ENV[TestEnvKeys::BullhornPassword]
        );

    }

    function testThrowsExceptionOnInvalidPassword()
    {
        $this->expectException(BullhornAuthException::class);
        $this->client->initiateSession(
            $_ENV[TestEnvKeys::BullhornUsername],
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
            $_ENV[TestEnvKeys::BullhornClientId],
            $_ENV[TestEnvKeys::BullhornClientSecret],
            $dataStore
        );
        $localClient->initiateSession(
            $_ENV[TestEnvKeys::BullhornUsername],
            $_ENV[TestEnvKeys::BullhornPassword]
        );
        $dataKey = $_ENV[TestEnvKeys::BullhornClientId] . '-refreshToken';
        $dataStore->store($dataKey, 'invalid-refresh-token');
        $localClient->refreshSession();
    }

    function testPassingTtlOptionSetsTtl()
    {
        $localClient = new Client(
            $_ENV[TestEnvKeys::BullhornClientId],
            $_ENV[TestEnvKeys::BullhornClientSecret],
            new MemoryDataStore(),
            ['autoRefresh' => false]
        );
        $localClient->initiateSession(
            $_ENV[TestEnvKeys::BullhornUsername],
            $_ENV[TestEnvKeys::BullhornPassword],
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

    function test_itCreatesAndDeletesEventSubscriptions()
    {
        $subscriptionName = 'TestSubscription';
        $response = $this->client->EventSubscription->create(
            $subscriptionName,
            'JobOrder',
            'INSERTED,UPDATED,DELETED'
        );
        $this->assertTrue(isset($response->createdOn));
        $response = $this->client->EventSubscription->delete('TestSubscription');
        $this->assertEquals(1, $response->result);
    }

    private function checkForValidResponse()
    {
        $response = $this->client->request('get', 'search/JobOrder');
        return isset($response->searchFields);
    }

    private function checkedClientRefresh(array $options = [])
    {
        $this->client->refreshOrInitiateSession(
            $_ENV[TestEnvKeys::BullhornUsername],
            $_ENV[TestEnvKeys::BullhornPassword],
            $options
        );
    }
}
