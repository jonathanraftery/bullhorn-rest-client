# Bullhorn REST Client

**Currently in beta status.**

Provides a simple client for the Bullhorn REST API.

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
```

### Raw Requests

Simple requests as documented in the Bullhorn API documentation can be run as:

```php
$response = $client->request(
    'GET',
    'search/JobOrder?query=id:7777'
);
```

### PUT/POST Requests

The client uses [GuzzleHTTP](http://docs.guzzlephp.org/en/stable/) for requests, and the parameters to the request method match those to create a request object in Guzzle. The third parameter is the request options, as described in the [Guzzle documentation](http://docs.guzzlephp.org/en/stable/request-options.html).

To set the body of a PUT/POST request, set the "json" option of the request to the JSON content of the request body such as:
 
```php
$response = $client->request(
    'PUT',
    'entity/Candidate',
    [
        'json' => json_encode(['firstName' => 'Alanzo', 'lastName' => 'Smith', 'status' => 'Registered'])
    ]
);
```

### Entity Requests

Raw requests are fun, but you can also use properties on the client to interact with specific entities. Most of the functionality for entities is available: create, update, delete, search, query, meta, and mass update.

The following will retrieve all job orders matching isDeleted:false

```php
$luceneConditions = 'isDeleted:false';
$fields = ['id', 'name', 'dateAdded']; // ['*'] = all fields, default is ['id']
$jobOrders = $client->JobOrders
    ->search([
        'query' => $luceneConditions
        'fields' => $fields
    ]);
```

Simply replace `JobOrders` with an alternative entity type. View the [entity documentation](https://bullhorn.github.io/rest-api-docs/entityref.html) on the different entities available as well as the fields for each entity. Note there are restrictions on what can be performed based on the API account.

```php
$newCandidate = $client->Candidate->create([
    'firstName' => 'Jane',
    'lastName' => 'Doe'
]);

// $newCandidate->changedEntityId will contain the newly created ID
```

### Event Subscriptions

You can manage your event subscriptions via the `eventSubscription(name)` function.

```php
// Create a subscription
$createdSub = $client->eventSubscription('MySub')
    ->create(['Lead', 'Candidate'], ['INSERT', 'UPDATE']);

// Get the subscription results
$results = $client->eventSubscription('MySub')->get(); 

// Delete the subscription
$client->eventSubscription('MySub')->delete();
``` 

### Session Timeouts

Session will automatically refresh if expiration detected, or can be refreshed manually (shown with optional parameters)

```php
$client->refreshSession(['ttl' => 60]);
```
