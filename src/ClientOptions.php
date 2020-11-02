<?php namespace jonathanraftery\Bullhorn\Rest;

abstract class ClientOptions {
    const CredentialsProvider = 'CredentialsProvider';
    const AuthClient = 'AuthClient';
    const AuthDataStore = 'AuthDataStore';
    const HttpClientFactory = 'HttpClientFactory';
    const AutoRefreshSessions = 'AutoRefreshSessions';
    const MaxSessionRefreshTries = 'MaxSessionRefreshTries';
}
