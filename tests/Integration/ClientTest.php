<?php namespace jonathanraftery\Bullhorn\Rest\Tests\Integration;

use jonathanraftery\Bullhorn\Rest\Auth\AuthClientOptions;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\BullhornAuthException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\CreateSessionException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidRefreshTokenException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\RestLoginException;
use jonathanraftery\Bullhorn\Rest\Auth\Store\MemoryDataStore;
use jonathanraftery\Bullhorn\Rest\BullhornEntities;
use jonathanraftery\Bullhorn\Rest\Client;
use jonathanraftery\Bullhorn\Rest\ClientOptions;
use jonathanraftery\Bullhorn\Rest\EventTypes;
use jonathanraftery\Bullhorn\Rest\Exception\BullhornClientException;
use jonathanraftery\Bullhorn\Rest\Exception\HttpException;
use jonathanraftery\Bullhorn\Rest\Exception\InvalidConfigException;
use jonathanraftery\Bullhorn\Rest\Tests\Mocks\InvalidTokenAuthClient;
use jonathanraftery\Bullhorn\Rest\Tests\Mocks\MockAuthClient;
use PHPUnit\Framework\TestCase;

/**
 * Bullhorn Client integration tests
 *
 * These tests will send _real_ requests to the Bullhorn API using your supplied credentials.
 * At the time of writing, Bullhorn does not offer sandbox accounts, so a real account must be used.
 * (See /test-util/.env.test.example for credential configuration)
 *
 * @group rest
 */
final class ClientTest extends TestCase {
    /** @var Client */ protected $client;

    /**
     * @throws BullhornAuthException
     * @throws InvalidConfigException
     */
    protected function setUp(): void {
        parent::setUp();
        $this->client = new Client([
            ClientOptions::AuthDataStore => new MemoryDataStore(),
        ]);
        $this->client->initiateSession();
    }

    /**
     * @throws BullhornClientException
     */
    function test_itMakesRawRequests() {
        $result = $this->client->rawRequest('GET', 'entity/CorporateUser/1', [
            'query' => ['fields' => 'id'],
        ]);
        $this->assertNotNull($result->getBody()->getContents());
    }

    /**
     * @throws BullhornClientException
     */
    function test_itFetchesEntities() {
        $result = $this->client->fetchEntities(BullhornEntities::JobOrder, [1,2,3], [
            'fields' => '*',
        ]);
        $this->assertGreaterThan(0, count($result));
    }

    /**
     * @throws BullhornClientException
     */
    function test_itCreatesAndDeletesEntities() {
        $testName = 'Test Candidate';
        $createResult = $this->client->createEntity(BullhornEntities::Candidate, [
            'firstName' => $testName,
        ]);
        $this->assertEquals('INSERT', $createResult->changeType);
        $this->assertNotNull($createResult->changedEntityId);
        $this->assertEquals($testName, $createResult->data->firstName);
        $deleteResult = $this->client->deleteEntity(BullhornEntities::Candidate, $createResult->changedEntityId);
        $this->assertEquals('DELETE', $deleteResult->changeType);
        $this->assertEquals($createResult->changedEntityId, $deleteResult->changedEntityId);
    }

    /**
     * @throws BullhornClientException
     */
    function test_itCreatesAndDeletesEventSubscriptions() {
        $subscriptionName = 'test-subscription';
        $createResult = $this->client->createEventSubscription($subscriptionName, [BullhornEntities::JobOrder], [EventTypes::Created]);
        $this->assertTrue(isset($createResult->createdOn));
        $deleteResult = $this->client->deleteEventSubscription($subscriptionName);
        $this->assertTrue($deleteResult->result);
    }

    /**
     * @throws BullhornClientException
     */
    function test_itFetchesEventSubscriptionEvents() {
        $subscriptionName = 'test-subscription';
        $this->client->createEventSubscription($subscriptionName, [BullhornEntities::Candidate], [EventTypes::Created]);
        $createdTestCandidate = $this->client->createEntity(BullhornEntities::Candidate, [
            'firstName' => 'Test Candidate',
        ]);

        $result = $this->client->fetchEventSubscriptionEvents($subscriptionName);
        $this->assertEquals(BullhornEntities::Candidate, $result->events[0]->entityName);
        $this->assertEquals($createdTestCandidate->changedEntityId, $result->events[0]->entityId);

        $this->client->deleteEventSubscription($subscriptionName);
        $this->client->deleteEntity(BullhornEntities::Candidate, $createdTestCandidate->changedEntityId);
    }

    /**
     * @throws BullhornAuthException
     * @throws CreateSessionException
     * @throws InvalidRefreshTokenException
     * @throws RestLoginException|InvalidConfigException
     */
    function test_itTrowsExceptionOnInvalidRefreshToken()
    {
        $dataStore = new MemoryDataStore();
        $client = new Client([
            ClientOptions::AuthDataStore => $dataStore
        ]);
        $client->initiateSession();
        $dataKey = $_ENV['BULLHORN_CLIENT_ID'] . '-refresh-token';
        $dataStore->store($dataKey, 'invalid-refresh-token');
        $this->expectException(InvalidRefreshTokenException::class);
        $client->refreshSession();
    }

//    /**
//     * @throws BullhornAuthException
//     * @throws HttpException
//     * @throws InvalidConfigException
//     * @throws \jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidConfigException
//     */
//    function test_onInvalidTokenUsed_itThrowsInvalidTokenException() {
//        $client = new Client([
//            ClientOptions::AuthClient => new InvalidTokenAuthClient([
//                AuthClientOptions::DataStore => new MemoryDataStore(),
//            ]),
//        ]);
//        $client->initiateSession();
//        $res = $client->fetchEntities(BullhornEntities::Candidate, [1,2,3], ['fields' => '*']);
//        print_r($res);
//    }

//    /**
//     * @group slow
//     * @throws InvalidConfigException
//     * @throws BullhornAuthException
//     * @throws HttpException
//     */
//    function test_onExpiredTokenUsed_itThrowsInvalidRestTokenException()
//    {
//        $client = new Client([
//            ClientOptions::AuthDataStore => new MemoryDataStore(),
//            ClientOptions::AutoRefreshSessions => false,
//        ]);
//        $client->initiateSession(['ttl' => 1]);
//        sleep(70);
////        $this->expectException(ExpiredTokenException::class);
//        $client->fetchEntities(BullhornEntities::Candidate, [1]);
//    }
//
//    /**
//     * @group slow
//     * @throws BullhornAuthException
//     * @throws BullhornClientException
//     */
//    function testRefreshesSessionIfExpirationDetected()
//    {
//        $this->client->initiateSession(['ttl' => 1]);
//        sleep(70);
//        $this->client->fetchEntities(BullhornEntities::Candidate, [1,2,3]);
//    }
}
