<?php namespace jonathanraftery\Bullhorn\Rest\Auth;

interface AuthClientInterface {
    function getRestToken(): ?string;
    function getRestUrl(): ?string;
    function getRefreshToken(): ?string;
    function initiateSession();
    function refreshSession();
    function sessionIsValid(): bool;
}
