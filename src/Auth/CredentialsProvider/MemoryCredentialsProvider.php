<?php namespace jonathanraftery\Bullhorn\Rest\Auth\CredentialsProvider;

/**
 * Class MemoryCredentialsProvider
 *
 * ** NOT RECOMMENDED FOR PRODUCTION USE FOR SECURITY REASONS **
 * Provides client credentials stored in memory.
 *
 * @package jonathanraftery\Bullhorn\Rest\CredentialsProvider
 */
class MemoryCredentialsProvider implements CredentialsProviderInterface {
    private $clientId;
    private $clientSecret;
    private $username;
    private $password;

    public function __construct(string $clientId, string $clientSecret, string $username, string $password) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->username = $username;
        $this->password = $password;
    }

    public function getClientId(): string {
        return $this->clientId;
    }

    public function getClientSecret(): string {
        return $this->clientSecret;
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function getPassword(): string {
        return $this->password;
    }
}
