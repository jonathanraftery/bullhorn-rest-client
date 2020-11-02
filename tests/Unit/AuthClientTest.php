<?php namespace jonathanraftery\Bullhorn\Rest\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use jonathanraftery\Bullhorn\Rest\Auth\AuthClient;
use jonathanraftery\Bullhorn\Rest\Auth\AuthClientOptions;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\BullhornAuthException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidClientIdException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidConfigException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidRefreshTokenException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidUserCredentialsException;
use jonathanraftery\Bullhorn\Rest\Auth\Store\DataStoreInterface;
use jonathanraftery\Bullhorn\Rest\Auth\Store\MemoryDataStore;
use jonathanraftery\Bullhorn\Rest\Tests\Mocks\FakeCredentialsProvider;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;

/**
 * AuthClient unit tests
 * @group auth
 * @group unit
 */
final class AuthClientTest extends TestCase {
    /** @var AuthClient */ private $authClient;
    /** @var DataStoreInterface */ private $dataStore;
    private $mockHttpHandler;

    /**
     * @param array $responses
     * @throws InvalidConfigException
     */
    private function setupMockHttp(array $responses = []) {
        $this->dataStore = new MemoryDataStore();
        $this->mockHttpHandler = new MockHandler();
        $httpClient = new GuzzleClient([
            'handler' => $this->mockHttpHandler,
        ]);
        $this->mockHttpHandler->append(...$responses);
        $this->authClient = new AuthClient([
            AuthClientOptions::CredentialsProvider => new FakeCredentialsProvider(),
            AuthClientOptions::DataStore => $this->dataStore,
            AuthClientOptions::HttpClient => $httpClient,
        ]);
    }

    /**
     * @throws InvalidConfigException
     */
    private function setupMockSuccessfulOauth() {
        $this->setupMockHttp([
            new Response(200, ['Location' => file_get_contents(__DIR__ . '/../Mocks/auth/auth-code-location-header.mock.txt')]),
            new Response(200, [], file_get_contents(__DIR__ . '/../Mocks/auth/access-token-success.mock.json')),
            new Response(200, [], file_get_contents(__DIR__ . '/../Mocks/auth/login-success.mock.json')),
        ]);
    }

    function test_itVerifiesInterfaceOfCredentialsProviderOption() {
        $this->expectException(InvalidConfigException::class);
        new AuthClient([AuthClientOptions::CredentialsProvider => 'invalid']);
    }

    function test_itVerifiesInterfaceOfDataStoreOption() {
        $this->expectException(InvalidConfigException::class);
        new AuthClient([AuthClientOptions::DataStore => 'invalid']);
    }

    function test_itVerifiesInterfaceOfHttpClient() {
        $this->expectException(InvalidConfigException::class);
        new AuthClient([AuthClientOptions::HttpClient => 'invalid']);
    }

    /**
     * @throws BullhornAuthException
     */
    function test_itInitiatesSessions() {
        $this->setupMockSuccessfulOauth();
        $this->authClient->initiateSession();
        $this->assertEquals('https://mock-rest.bullhornstaffing.com/rest-services/mock/', $this->authClient->getRestUrl());
        $this->assertEquals('mock-rest-token', $this->authClient->getRestToken());
        $this->assertEquals('mock-refresh-token', $this->authClient->getRefreshToken());
    }

    /**
     * @throws BullhornAuthException
     */
    function test_itRefreshesSessions()
    {
        $this->setupMockSuccessfulOauth();
        $this->authClient->initiateSession();
        $this->mockHttpHandler->append(...[
            new Response(200, [], file_get_contents(__DIR__ . '/../Mocks/auth/access-token-success.mock.json')),
            new Response(200, [], str_replace('mock-rest-token', 'second-rest-token', file_get_contents(__DIR__ . '/../Mocks/auth/login-success.mock.json'))),
        ]);
        $this->authClient->refreshSession();
        $this->assertTrue($this->authClient->sessionIsValid());
        $this->assertEquals('second-rest-token', $this->authClient->getRestToken());
    }

    /**
     * @throws BullhornAuthException
     */
    function test_onConstruction_itUsesExistingSessionFromDataStore()
    {
        $this->setupMockSuccessfulOauth();
        $this->authClient->initiateSession();
        $secondClient = new AuthClient([
            AuthClientOptions::CredentialsProvider => new FakeCredentialsProvider(),
            AuthClientOptions::DataStore => $this->dataStore
        ]);
        $this->assertEquals($this->authClient->getRestToken(), $secondClient->getRestToken());
    }

    /**
     * @throws BullhornAuthException
     */
    function test_onSessionInit_invalidCredentialsResponse_throwsInvalidUserCredentialsException() {
        $this->setupMockHttp([
            new Response(200, [], file_get_contents(__DIR__ . '/../Mocks/auth/invalid-credentials-response.mock.html'))
        ]);
        $this->expectException(InvalidUserCredentialsException::class);
        $this->authClient->initiateSession();
    }

    /**
     * @throws BullhornAuthException
     */
    function test_onSessionInit_invalidClientResponse_throwsInvalidClientIdException() {
        $this->setupMockHttp([
            new Response(200, [], file_get_contents(__DIR__ . '/../Mocks/auth/invalid-client-id-response.mock.html'))
        ]);
        $this->expectException(InvalidClientIdException::class);
        $this->authClient->initiateSession();
    }

    /**
     * @throws BullhornAuthException
     */
    function testThrowsExceptionOnInvalidRefreshToken()
    {
        $this->setupMockSuccessfulOauth();
        $this->authClient->initiateSession();
        $this->mockHttpHandler->append(...[
            new Response(400, [], '{"error": "mock error"}'),
            new Response(200, [], file_get_contents(__DIR__ . '/../Mocks/auth/login-success.mock.json')),
        ]);
        $this->expectException(InvalidRefreshTokenException::class);
        $this->authClient->refreshSession();
    }
}
