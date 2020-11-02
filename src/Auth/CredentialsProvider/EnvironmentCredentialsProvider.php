<?php namespace jonathanraftery\Bullhorn\Rest\Auth\CredentialsProvider;

use jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidConfigException;

/**
 * Class EnvironmentCredentialsProvider
 *
 * Provides client credentials stored in the environment.
 *
 * @package jonathanraftery\Bullhorn\Rest\CredentialsProvider
 */
class EnvironmentCredentialsProvider implements CredentialsProviderInterface {
    private $idKey;
    private $secretKey;
    private $usernameKey;
    private $passwordKey;

    /**
     * EnvironmentCredentialsProvider constructor.
     * @param string $idKey
     * @param string $secretKey
     * @param string $usernameKey
     * @param string $passwordKey
     * @throws InvalidConfigException
     */
    public function __construct(string $idKey = 'BULLHORN_CLIENT_ID',
                                string $secretKey = 'BULLHORN_CLIENT_SECRET',
                                string $usernameKey = 'BULLHORN_USERNAME',
                                string $passwordKey = 'BULLHORN_PASSWORD'
    ) {
        if (!$_ENV[$idKey] || !$_ENV[$secretKey] || !$_ENV[$usernameKey] || !$_ENV[$passwordKey]) {
            throw new InvalidConfigException(EnvironmentCredentialsProvider::class . ' used without environment variables set');
        }

        $this->idKey = $idKey;
        $this->secretKey = $secretKey;
        $this->usernameKey = $usernameKey;
        $this->passwordKey = $passwordKey;
    }

    public function getClientId(): string {
        return $_ENV[$this->idKey];
    }

    public function getClientSecret(): string {
        return $_ENV[$this->secretKey];
    }

    public function getUsername(): string {
        return $_ENV[$this->usernameKey];
    }

    public function getPassword(): string {
        return $_ENV[$this->passwordKey];
    }
}
