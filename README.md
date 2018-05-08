# Bullhorn REST Client

This package provides a simple client for the Bullhorn REST API.

## Installation
``` bash
$ composer require jonathanraftery/bullhorn-rest-client
```

## Usage
```php
use jonathanraftery\Bullhorn\Rest\Client as BullhornClient;

$client = new BullhornClient(
    'client-id',
    'client-secret'
);
$client->initiateSession(
    'username',
    'password'
);

$response = $client->request(
    'GET',
    'search/JobOrder',
    ['query' => 'id:7777']
);

// session will automatically refresh if expiration detected
// or can be refreshed manually (shown with optional parameters)
$client->refreshSession(['ttl' => 60]);
```
