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
    'client_id',
    'client_secret',
    'bullhorn_username',
    'bullhorn_password'
);

$response = $client->get(
    'search/JobOrder',
    ['query' => 'id:7777']
);
```
