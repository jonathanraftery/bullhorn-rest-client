<?php namespace jonathanraftery\Bullhorn\Rest\Tests\Mocks;

use jonathanraftery\Bullhorn\Rest\Auth\CredentialsProvider\CredentialsProviderInterface;

class FakeCredentialsProvider implements CredentialsProviderInterface {
    const CLIENT_ID = 'fake-client-id';
    const CLIENT_SECRET = 'fake-client-secret';
    const USERNAME = 'fake-username';
    const PASSWORD = 'fake-password';

    public function getClientId(): string {
        return self::CLIENT_ID;
    }

    public function getClientSecret(): string {
        return self::CLIENT_SECRET;
    }

    public function getUsername(): string {
        return self::USERNAME;
    }

    public function getPassword(): string {
        return self::PASSWORD;
    }
}
