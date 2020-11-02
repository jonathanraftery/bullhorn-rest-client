<?php namespace jonathanraftery\Bullhorn\Rest\Auth\CredentialsProvider;

/**
 * Interface CredentialsProviderInterface
 *
 * A credentials provider is used to fetch credentials for the Bullhorn API. Simple implementations are provided,
 * or you can create your own to interface with eg. Google Cloud Secret Manager
 *
 * @package jonathanraftery\Bullhorn\Rest\Auth\CredentialsProvider
 */
interface CredentialsProviderInterface {
    function getClientId(): string;
    function getClientSecret(): string;
    function getUsername(): string;
    function getPassword(): string;
}
