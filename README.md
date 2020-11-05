# Bullhorn REST Client

Provides a simple client for the Bullhorn REST API.

## Installation
``` bash
$ composer require jonathanraftery/bullhorn-rest-client
```

## Usage
```php
use jonathanraftery\Bullhorn\Rest\Client as BullhornClient;
$client = new BullhornClient();
```

By default, the client will look for credentials in environment variables:
- BULLHORN_CLIENT_ID
- BULLHORN_CLIENT_SECRET
- BULLHORN_USERNAME
- BULLHORN_PASSWORD

### Options
The client constructor accepts an option array.
```php
use jonathanraftery\Bullhorn\Rest\Auth\CredentialsProvider\MemoryCredentialsProvider;
use jonathanraftery\Bullhorn\Rest\Client as BullhornClient;
use jonathanraftery\Bullhorn\Rest\ClientOptions;

$client = new BullhornClient([
    // NOTE: MemoryCredentialProvider not recommended for production
    ClientOptions::CredentialsProvider => new MemoryCredentialsProvider(
        'clientId', 'clientSecret', 'username', 'password'
    ),
]);
```
The ClientOptions class can be used to view the available options.

### Credential Providers
A credential provider supplies the client with the credentials needed to
connect to the API. There are simple providers included, or you can
create your own with a class implementing the CredentialsProviderInterface
(for example, to fetch credentials from Google Cloud Secret Manager).
```php
use jonathanraftery\Bullhorn\Rest\Client as BullhornClient;
use jonathanraftery\Bullhorn\Rest\ClientOptions;
use jonathanraftery\Bullhorn\Rest\Auth\CredentialsProvider\CredentialsProviderInterface;

class CustomCredentialProvider implements CredentialsProviderInterface {
    public function getClientId() : string{ return 'id'; }
    public function getClientSecret() : string{ return 'secret'; }
    public function getUsername() : string{ return 'username'; }
    public function getPassword() : string{ return 'password'; }
}

$client = new BullhornClient([
    ClientOptions::CredentialsProvider => new CustomCredentialProvider()
]);
```

By default, the client will use an EnvironmentCredentialsProvider, which will
look in the environment variables listed above for credentials. The variables
used can be changed by constructing an EnvironmentCredentialsProvider with
the other variables.

### Auth Data Stores
API session data needs persisted in a data store. By default, the client
will use a local JSON file for this store, but custom stores can be used.
```php
use jonathanraftery\Bullhorn\Rest\Client as BullhornClient;
use jonathanraftery\Bullhorn\Rest\ClientOptions;
use jonathanraftery\Bullhorn\Rest\Auth\Store\DataStoreInterface;

class CustomDataStore implements DataStoreInterface {
    private $vars;

    public function get(string $key) : ?string{
        return $this->vars[$key];
    }

    public function store(string $key,$value){
        $this->vars[$key] = $value;
    }
}

$client = new BullhornClient([
    ClientOptions::AuthDataStore => new CustomDataStore()
]);
```

### Initial OAuth consent
Before Bullhorn authorizes API calls from a new user, the user is required to give consent. If no consent has been given yet the library will throw an IdentityException and the client will respond with an HTML representation of the consent form.

To permanently fix this, visit the authorization URL with your credentials [auth.bullhornstaffing.com/oauth/authorize?response_type=code&action=Login&username=<username>&password=<password>&state=<client_secret>&approval_prompt=auto&client_id=<client_id>](https://auth.bullhornstaffing.com/oauth/authorize?response_type=code&action=Login&username=<username>&password=<password>&state=<client_secret>&approval_prompt=auto&client_id=<client_id>) while logged into bullhorn and press the **Agree** button. This will authorize your application to use the API in the user's name.

### Raw Requests
Simple requests as documented in the Bullhorn API documentation can be run as:
```php
use jonathanraftery\Bullhorn\Rest\Client as BullhornClient;
$client = new BullhornClient();
$response = $client->rawRequest(
    'GET',
    'search/JobOrder',
    [
        'query' => 'id:1234'
    ]
);
```

### PUT/POST Requests
The client uses [GuzzleHTTP](http://docs.guzzlephp.org/en/stable/) for requests, and the parameters to the request method match those to create a request object in Guzzle. The third parameter is the request options, as described in the [Guzzle documentation](http://docs.guzzlephp.org/en/stable/request-options.html).

To set the body of a PUT/POST request, set the "body" option of the request to the JSON content of the request body such as:
```php
use jonathanraftery\Bullhorn\Rest\Client as BullhornClient;
$client = new BullhornClient();
$response = $client->rawRequest(
    'PUT',
    'entity/Candidate',
    [
        'body' => json_encode(['firstName' => 'Alanzo', 'lastName' => 'Smith', 'status' => 'Registered'])
    ]
);
```

### Entity Operations
Entities can be fetched, created, and deleted
```php
use jonathanraftery\Bullhorn\Rest\Client as BullhornClient;
use jonathanraftery\Bullhorn\Rest\BullhornEntities;
$client = new BullhornClient();
$fetchedJobOrders = $client->fetchEntities(BullhornEntities::JobOrder, [1,2,3], [
    'fields' => 'id',
]);
$createdJobOrder = $client->createEntity(BullhornEntities::JobOrder, [
    'propName' => 'value',
    'propName2' => 'value',
]);
$deleteId = 1;
$client->deleteEntity(BullhornEntities::JobOrder, $deleteId);
```

### Event Subscriptions
```php
use jonathanraftery\Bullhorn\Rest\Client as BullhornClient;
use jonathanraftery\Bullhorn\Rest\BullhornEntities;
use jonathanraftery\Bullhorn\Rest\EventTypes;
$client = new BullhornClient();
$client->createEventSubscription('SubscriptionName', [BullhornEntities::JobOrder], [EventTypes::Created]);
$client->fetchEventSubscriptionEvents('SubscriptionName');
$client->deleteEventSubscription('SubscriptionName');
```

### Sessions
A session will automatically be initiated upon the first request if no
session exists in the data store.

A session can be manually initiated with:
```php
use jonathanraftery\Bullhorn\Rest\Client as BullhornClient;
$client = new BullhornClient();
$client->initiateSession();
```

The session will automatically refresh if expiration detected, or can be refreshed manually (shown with optional parameters)
```php
use jonathanraftery\Bullhorn\Rest\Client as BullhornClient;
$client = new BullhornClient();
$client->refreshSession(['ttl' => 60]);
```
