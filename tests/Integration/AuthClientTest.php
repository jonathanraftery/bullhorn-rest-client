<?php namespace jonathanraftery\Bullhorn\Rest\Tests\Integration;

use Exception;
use jonathanraftery\Bullhorn\Rest\Auth\AuthClient;
use jonathanraftery\Bullhorn\Rest\Auth\AuthClientOptions;
use jonathanraftery\Bullhorn\Rest\Auth\CredentialsProvider\EnvironmentCredentialsProvider;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\BullhornAuthException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidConfigException;
use jonathanraftery\Bullhorn\Rest\Auth\Store\MemoryDataStore;
use PHPUnit\Framework\TestCase;

/**
 * AuthClient integration tests
 *
 * These tests will send _real_ requests to the Bullhorn API using your supplied credentials.
 * At the time of writing, Bullhorn does not offer sandbox accounts, so a real account must be used.
 * (See /test-util/.env.test.example for credential configuration)
 *
 * @group auth
 * @group integration
 */
final class AuthClientTest extends TestCase {
    /** @var AuthClient */ private $authClient;

    /**
     * @throws InvalidConfigException
     */
    protected function setUp(): void {
        parent::setUp();
        $this->authClient = new AuthClient([
            AuthClientOptions::CredentialsProvider => new EnvironmentCredentialsProvider(),
            AuthClientOptions::DataStore => new MemoryDataStore()
        ]);
    }

    /**
     * @throws BullhornAuthException
     * @throws Exception
     */
    function test_itInitiatesRestSessions() {
        $this->authClient->initiateSession();
        $this->assertNotNull($this->authClient->getRestUrl());
        $this->assertNotNull($this->authClient->getRestToken());
        $this->assertNotNull($this->authClient->getRefreshToken());
    }

    /**
     * @throws BullhornAuthException
     * @throws Exception
     */
    function test_itRefreshesRestSessions() {
        $this->authClient->initiateSession();
        $firstToken = $this->authClient->getRestToken();
        $this->authClient->refreshSession();
        $this->assertNotEquals($firstToken, $this->authClient->getRestToken());
    }
}
