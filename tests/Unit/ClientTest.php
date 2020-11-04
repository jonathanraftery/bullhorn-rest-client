<?php namespace jonathanraftery\Bullhorn\Rest\Tests\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\BullhornAuthException;
use jonathanraftery\Bullhorn\Rest\Auth\Store\MemoryDataStore;
use jonathanraftery\Bullhorn\Rest\BullhornEntities;
use jonathanraftery\Bullhorn\Rest\Client;
use jonathanraftery\Bullhorn\Rest\ClientOptions;
use jonathanraftery\Bullhorn\Rest\Exception\BullhornClientException;
use jonathanraftery\Bullhorn\Rest\Exception\HttpException;
use jonathanraftery\Bullhorn\Rest\Exception\InvalidConfigException;
use jonathanraftery\Bullhorn\Rest\Tests\Mocks\FakeCredentialsProvider;
use jonathanraftery\Bullhorn\Rest\Tests\Mocks\MockAuthClient;
use PHPUnit\Framework\TestCase;

/**
 * @group rest
 * @group unit
 */
final class ClientTest extends TestCase {
    /** @var Client */ private $client;
    /** @var MockHandler */ private $mockHttpHandler;
    private $httpHistoryRecords;

    /**
     * @throws InvalidConfigException
     * @throws \jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidConfigException|BullhornAuthException
     */
    protected function setUp(): void {
        parent::setUp();
        $this->mockHttpHandler = new MockHandler();
        $this->httpHistoryRecords = [];
        $httpHistory = Middleware::history($this->httpHistoryRecords);
        $handlerStack = HandlerStack::create($this->mockHttpHandler);
        $handlerStack->push($httpHistory);
        $this->client = new Client([
            ClientOptions::AuthClient => new MockAuthClient(),
            ClientOptions::HttpClientFactory => function() use ($handlerStack) {
                return new \GuzzleHttp\Client([
                    'handler' => $handlerStack,
                ]);
            }
        ]);
    }

    function test_itProvidesCurrentSessionData() {
        $this->assertNotNull($this->client->getRestToken());
        $this->assertNotNull($this->client->getRestUrl());
    }

    /**
     * @throws InvalidConfigException
     * @throws \jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidConfigException|BullhornAuthException
     */
    function test_ifAuthClientAndDataStoreOptionsSupplied_throwsException() {
        $this->expectException(InvalidConfigException::class);
        new Client([
            ClientOptions::AuthDataStore => new MemoryDataStore(),
            ClientOptions::AuthClient => new MockAuthClient(),
        ]);
    }

    /**
     * @throws InvalidConfigException
     * @throws \jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidConfigException|BullhornAuthException
     */
    function test_itThrowsExceptionIfAuthClientAndCredentialsProviderOptionsProvided() {
        $this->expectException(InvalidConfigException::class);
        new Client([
            ClientOptions::CredentialsProvider => new FakeCredentialsProvider(),
            ClientOptions::AuthClient => new MockAuthClient(),
        ]);
    }

    /**
     * @throws BullhornClientException
     * @throws BullhornAuthException
     */
    function test_itFetchesEntities() {
        $this->client->initiateSession();
        $this->mockHttpHandler->append(
            new Response(200, [], file_get_contents(__DIR__ . '/../Mocks/fetch-job-orders.mock.json')),
        );
        $fetchedJobOrders = $this->client->fetchEntities(BullhornEntities::JobOrder, [3], [
            'fields' => '*'
        ]);

        $request = $this->httpHistoryRecords[0]['request'];
        $this->assertCount(1, $this->httpHistoryRecords);
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('entity/JobOrder/3', $request->getUri()->getPath());
        $this->assertEquals('fields=%2A', $request->getUri()->getQuery());

        $this->assertCount(1, $fetchedJobOrders);
        $this->assertEquals(3, $fetchedJobOrders[0]->id);
    }

    /**
     * @throws HttpException
     */
    function test_itSearchesEntities() {
        $this->mockHttpHandler->append(
            new Response(200, [], file_get_contents(__DIR__ . '/../Mocks/search-candidates.mock.json'))
        );
        $result = $this->client->searchEntities(BullhornEntities::Candidate, 'isDeleted:0', [
            'fields' => 'id',
            'count' => 1
        ]);
        $this->assertNotNull($result->data[0]->id);
    }
}
