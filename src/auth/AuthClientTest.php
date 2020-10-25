<?php

use GuzzleHttp\Psr7\Response;
use jonathanraftery\Bullhorn\Rest\Authentication\AuthClientCollaboratorKey;
use jonathanraftery\Bullhorn\Rest\Authentication\BullhornAuthException;
use jonathanraftery\Bullhorn\Rest\Authentication\DataStore;
use jonathanraftery\Bullhorn\Rest\Authentication\InvalidClientIdException;
use jonathanraftery\Bullhorn\Rest\Authentication\InvalidRefreshTokenException;
use jonathanraftery\Bullhorn\Rest\Authentication\InvalidUserCredentialsException;
use PHPUnit\Framework\TestCase;
use jonathanraftery\Bullhorn\Rest\Authentication\AuthClient;
use jonathanraftery\Bullhorn\Rest\Authentication\MemoryDataStore;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;

/**
 * Auth Client Tests
 * @group auth
 */
final class AuthClientTest extends TestCase {
    /** @var AuthClient */ private $authClient;
    /** @var DataStore */ private $dataStore;
    private $mockHttpHandler;

    private function setupRealHttp() {
        $this->dataStore = new MemoryDataStore();
        $this->authClient = new AuthClient(
            $_ENV[TestEnvKeys::BullhornClientId],
            $_ENV[TestEnvKeys::BullhornClientSecret],
            [AuthClientCollaboratorKey::DataStore => $this->dataStore]
        );
    }

    private function setupMockHttp(array $responses = []) {
        $this->dataStore = new MemoryDataStore();
        $this->mockHttpHandler = new MockHandler();
        $httpClient = new GuzzleClient([
            'handler' => $this->mockHttpHandler,
        ]);
        $this->mockHttpHandler->append(...$responses);
        $this->authClient = new AuthClient(
            $_ENV[TestEnvKeys::BullhornClientId],
            $_ENV[TestEnvKeys::BullhornClientSecret],
            [
                AuthClientCollaboratorKey::DataStore => $this->dataStore,
                AuthClientCollaboratorKey::HttpClient => $httpClient,
            ]
        );
    }

    private function setupMockSuccessfulOauth() {
        $this->setupMockHttp([
            new Response(200, ['Location' => file_get_contents(__DIR__ . '/mocks/auth-code-location-header.mock.txt')]),
            new Response(200, [], file_get_contents(__DIR__ . '/mocks/access-token-success.mock.json')),
            new Response(200, [], file_get_contents(__DIR__ . '/mocks/login-success.mock.json')),
        ]);
    }

    /**
     * @throws BullhornAuthException
     */
    private function initiateClientSession() {
        $this->authClient->initiateSession($_ENV[TestEnvKeys::BullhornUsername], $_ENV[TestEnvKeys::BullhornPassword]);
    }

    /**
     * @group integration
     * @throws BullhornAuthException
     * @throws Exception
     */
    function test_itInitiatesRealRestSessions() {
        $this->setupRealHttp();
        $this->initiateClientSession();
        $this->assertNotNull($this->authClient->getRestUrl());
        $this->assertNotNull($this->authClient->getRestToken());
        $this->assertNotNull($this->authClient->getRefreshToken());
    }

    /**
     * @group integration
     * @throws BullhornAuthException
     * @throws Exception
     */
    function test_itRefreshesRealRestSessions() {
        $this->setupRealHttp();
        $this->initiateClientSession();
        $firstToken = $this->authClient->getRestToken();
        $this->authClient->refreshSession();
        $this->assertNotEquals($firstToken, $this->authClient->getRestToken());
    }

    /**
     * @group unit
     * @throws BullhornAuthException
     */
    function test_itInitiatesMockSessions() {
        $this->setupMockSuccessfulOauth();
        $this->initiateClientSession();
        $this->assertEquals('https://mock-rest.bullhornstaffing.com/rest-services/mock/', $this->authClient->getRestUrl());
        $this->assertEquals('mock-rest-token', $this->authClient->getRestToken());
        $this->assertEquals('mock-refresh-token', $this->authClient->getRefreshToken());
    }

    /**
     * @group unit
     * @throws BullhornAuthException
     */
    function test_itRefreshesMockSessions()
    {
        $this->setupMockSuccessfulOauth();
        $this->initiateClientSession();
        $this->mockHttpHandler->append(...[
            new Response(200, [], file_get_contents(__DIR__ . '/mocks/access-token-success.mock.json')),
            new Response(200, [], str_replace('mock-rest-token', 'second-rest-token', file_get_contents(__DIR__ . '/mocks/login-success.mock.json'))),
        ]);
        $this->authClient->refreshSession();
        $this->assertTrue($this->authClient->sessionIsValid());
        $this->assertEquals('second-rest-token', $this->authClient->getRestToken());
    }

    /**
     * @group unit
     * @throws BullhornAuthException
     */
    function test_onConstruction_itUsesExistingSessionFromDataStore()
    {
        $this->setupMockSuccessfulOauth();
        $this->initiateClientSession();
        $secondClient = new AuthClient($_ENV[TestEnvKeys::BullhornClientId], $_ENV[TestEnvKeys::BullhornClientSecret], [
            AuthClientCollaboratorKey::DataStore => $this->dataStore
        ]);
        $this->assertEquals($this->authClient->getRestToken(), $secondClient->getRestToken());
    }

    /**
     * @group unit
     * @throws BullhornAuthException
     */
    function test_onSessionInit_invalidCredentialsResponse_throwsInvalidUserCredentialsException() {
        $this->setupMockHttp([
            new Response(200, [], file_get_contents(__DIR__ . '/mocks/invalid-credentials-response.mock.html'))
        ]);
        $this->expectException(InvalidUserCredentialsException::class);
        $this->authClient->initiateSession($_ENV[TestEnvKeys::BullhornUsername], $_ENV[TestEnvKeys::BullhornPassword]);
    }

    /**
     * @group unit
     * @throws BullhornAuthException
     */
    function test_onSessionInit_invalidClientResponse_throwsInvalidClientIdException() {
        $this->setupMockHttp([
            new Response(200, [], file_get_contents(__DIR__ . '/mocks/invalid-client-id-response.mock.html'))
        ]);
        $this->expectException(InvalidClientIdException::class);
        $this->initiateClientSession();
    }

    /**
     * @group unit
     * @throws BullhornAuthException
     */
    function testThrowsExceptionOnInvalidRefreshToken()
    {
        $this->setupMockSuccessfulOauth();
        $this->initiateClientSession();
        $this->mockHttpHandler->append(...[
            new Response(400, [], '{"error": "mock error"}'),
            new Response(200, [], file_get_contents(__DIR__ . '/mocks/login-success.mock.json')),
        ]);
        $this->expectException(InvalidRefreshTokenException::class);
        $this->authClient->refreshSession();
    }
}
