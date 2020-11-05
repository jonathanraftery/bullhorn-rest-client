<?php namespace jonathanraftery\Bullhorn\Rest\Auth;

abstract class AuthClientOptions {
    const CredentialsProvider = 'CredentialsProvider';
    const DataStore = 'DataStore';
    const HttpClient = 'HttpClient';
    const RestTokenStorageKey = 'RestTokenStorageKey';
    const RestUrlStorageKey = 'RestUrlStorageKey';
    const RefreshTokenStorageKey = 'RefreshTokenStorageKey';
}
