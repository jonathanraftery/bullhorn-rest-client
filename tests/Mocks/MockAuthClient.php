<?php namespace jonathanraftery\Bullhorn\Rest\Tests\Mocks;

use jonathanraftery\Bullhorn\Rest\Auth\AuthClientInterface;

class MockAuthClient implements AuthClientInterface {
    const REST_TOKEN = 'mock-rest-token-{{session-count}}';
    const REST_URL = 'https://bullhorn.com/rest/mock-{{session-count}}';
    const REFRESH_TOKEN = 'mock-refresh-token-{{session-count}}';

    protected $sessionsInitiated = 0;

    function getRestToken(): ?string {
        return $this->sessionsInitiated > 0
            ? str_replace('{{session-count}}', $this->sessionsInitiated, self::REST_TOKEN)
            : null;
    }

    function getRestUrl(): ?string {
        return $this->sessionsInitiated > 0
            ? str_replace('{{session-count}}', $this->sessionsInitiated, self::REST_URL)
            :null;
    }

    function getRefreshToken(): ?string {
        return $this->sessionsInitiated > 0
            ? str_replace('{{session-count}}', $this->sessionsInitiated, self::REFRESH_TOKEN)
            : null;
    }

    function initiateSession() {
        ++$this->sessionsInitiated;
    }

    function refreshSession() {
        ++$this->sessionsInitiated;
    }

    function sessionIsValid(): bool {
        return $this->sessionsInitiated > 0;
    }
}
