<?php namespace jonathanraftery\Bullhorn\Rest\Tests\Mocks;

use jonathanraftery\Bullhorn\Rest\Auth\AuthClient;

class InvalidTokenAuthClient extends AuthClient {
    public function getRestToken(): ?string {
        return 'fake';
    }
}
