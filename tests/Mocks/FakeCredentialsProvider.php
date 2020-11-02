<?php namespace jonathanraftery\Bullhorn\Rest\Tests\Mocks;

use jonathanraftery\Bullhorn\Rest\Auth\CredentialsProvider\CredentialsProviderInterface;

class FakeCredentialsProvider implements CredentialsProviderInterface {
    public function getClientId(): string {
        return 'fake';
    }

    public function getClientSecret(): string {
        return 'fake';
    }

    public function getUsername(): string {
        return 'fake';
    }

    public function getPassword(): string {
        return 'fake';
    }
}
