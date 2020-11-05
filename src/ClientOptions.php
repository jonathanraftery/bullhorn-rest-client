<?php namespace jonathanraftery\Bullhorn\Rest;

abstract class ClientOptions {
    const CredentialsProvider = 'CredentialsProvider';
    const AuthClient = 'AuthClient';
    const AuthDataStore = 'AuthDataStore';
    const RestTokenStorageKey = 'RestTokenStorageKey';
    const RestUrlStorageKey = 'RestUrlStorageKey';
    const RefreshTokenStorageKey = 'RefreshTokenStorageKey';
    const HttpClientFactory = 'HttpClientFactory';
    const AutoRefreshSessions = 'AutoRefreshSessions';
    const MaxSessionRefreshTries = 'MaxSessionRefreshTries';
}
