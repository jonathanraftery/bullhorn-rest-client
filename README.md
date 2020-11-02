# Bullhorn REST Client

**Currently in beta status.**

Provides a simple client for the Bullhorn REST API.

## Installation
``` bash
$ composer require jonathanraftery/bullhorn-rest-client
```

## Usage
```php
use jonathanraftery\Bullhorn\Rest\Exception\exceptions\exceptions\exceptions\Client as BullhornClient;

$client = new BullhornClient(
    'client-id',
    'client-secret'
);
$client->initiateSession(
    'username',
    'password'
);
```

### Initial OAuth consent

Before Bullhorn authorizes API calls from a new user, the user is required to give consent. If no consent has been given yet the library will throw an IdentityException and the client will respond with an HTML representation of the consent form.

To permanently fix this, visit the authorization URL with your credentials [auth.bullhornstaffing.com/oauth/authorize?response_type=code&action=Login&username=<username>&password=<password>&state=<client_secret>&approval_prompt=auto&client_id=<client_id>](https://auth.bullhornstaffing.com/oauth/authorize?response_type=code&action=Login&username=<username>&password=<password>&state=<client_secret>&approval_prompt=auto&client_id=<client_id>) while logged into bullhorn and press the **Agree** button. This will authorize your application to use the API in the user's name.

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

To set the body of a PUT/POST request, set the "body" option of the request to the JSON content of the request body such as:
```php
$response = $client->request(
    'PUT',
    'entity/Candidate',
    [
        'body' => json_encode(['firstName' => 'Alanzo', 'lastName' => 'Smith', 'status' => 'Registered'])
    ]
);
```

### Entity Requests
More complex requests can be used for specific entities. The following will retrieve all job orders matching isDeleted:false.

If there are more job orders than Bullhorn will return in one request, the client will automatically make multiple requests and return the concatenated array of all job orders.
```php
$luceneConditions = 'isDeleted:false';
$fields = ['id','name','dateAdded']; // ['*'] = all fields, default is ['id']
$jobOrders = $client->JobOrders->search($luceneConditions, $fields);
```
Currently, only job order entities are supported. The Resources/JobOrders class can be used as a reference of how to create others.

### Session Timeoutes
Session will automatically refresh if expiration detected, or can be refreshed manually (shown with optional parameters)
```php
$client->refreshSession(['ttl' => 60]);
```
