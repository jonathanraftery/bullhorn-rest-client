<?php namespace jonathanraftery\Bullhorn\Rest\Tests\Mocks;

use jonathanraftery\Bullhorn\Rest\Auth\AuthClientInterface;

class MockAuthClient implements AuthClientInterface {
    const REST_TOKEN = 'mock-rest-token';
    const REST_URL = 'https://bullhorn.com/rest/mock';
    const REFRESH_TOKEN = 'mock-refresh-token';

    function getRestToken(): ?string {
        return self::REST_TOKEN;
    }

    function getRestUrl(): ?string {
        return self::REST_URL;
    }

    function getRefreshToken(): ?string {
        return self::REFRESH_TOKEN;
    }

    function initiateSession() { }
}
