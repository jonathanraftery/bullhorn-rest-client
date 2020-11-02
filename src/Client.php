<?php namespace jonathanraftery\Bullhorn\Rest;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use InvalidArgumentException;
use GuzzleHttp\Client as GuzzleClient;
use jonathanraftery\Bullhorn\Rest\Auth\AuthClient;
use jonathanraftery\Bullhorn\Rest\Auth\AuthClientInterface;
use jonathanraftery\Bullhorn\Rest\Auth\AuthClientOptions;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidRefreshTokenException;
use jonathanraftery\Bullhorn\Rest\Auth\Store\LocalFileDataStore;
use jonathanraftery\Bullhorn\Rest\Exception\HttpException;
use jonathanraftery\Bullhorn\Rest\Exception\InvalidConfigException;
use Psr\Http\Message\ResponseInterface;

/**
 * Client for the Bullhorn REST API
 * @package jonathanraftery\Bullhorn\Rest
 */
class Client
{
    /** @var AuthClientInterface */ protected $authClient;
    /** @var GuzzleClient */ protected $httpClient;
    /** @var callable  */ protected $httpClientFactory;
    protected $shouldAutoRefreshSessions;
    protected $maxSessionRefreshTries;

    /**
     * Client constructor.
     * @param array $options
     * @throws InvalidConfigException
     * @throws Auth\Exception\InvalidConfigException
     */
    public function __construct(array $options) {
        if (array_key_exists(ClientOptions::AuthDataStore, $options)
            && array_key_exists(ClientOptions::AuthClient, $options)
        ) {
            throw new InvalidConfigException('Provide only ' . ClientOptions::AuthDataStore . ' or ' . ClientOptions::AuthClient . ' options');
        }
        if (array_key_exists(ClientOptions::CredentialsProvider, $options)
            && array_key_exists(ClientOptions::AuthClient, $options)
        ) {
            throw new InvalidConfigException('Provide only ' . ClientOptions::CredentialsProvider . ' or ' . ClientOptions::AuthClient . ' options');
        }

        $authClientOptions = [];
        if (array_key_exists(ClientOptions::CredentialsProvider, $options)) {
            $authClientOptions[AuthClientOptions::CredentialsProvider] = $options[ClientOptions::CredentialsProvider];
        }
        if (array_key_exists(ClientOptions::AuthDataStore, $options)) {
            $authClientOptions[AuthClientOptions::DataStore] = $options[ClientOptions::AuthDataStore];
        }
        $this->authClient = array_key_exists(ClientOptions::AuthClient, $options)
            ? $options[ClientOptions::AuthClient]
            : new AuthClient($authClientOptions)
        ;

        if (array_key_exists(ClientOptions::HttpClientFactory, $options)
            && !is_callable($options[ClientOptions::HttpClientFactory])
        ) {
            throw new InvalidArgumentException(ClientOptions::HttpClientFactory . ' must be a callable');
        }
        $this->httpClientFactory = array_key_exists(ClientOptions::HttpClientFactory, $options)
            ? $options[ClientOptions::HttpClientFactory]
            : function ($httpOptions) {return new GuzzleClient($httpOptions);};
        $this->setupHttpClient();

        $this->shouldAutoRefreshSessions = $options[ClientOptions::AutoRefreshSessions] ?? true;
        $this->maxSessionRefreshTries = $options[ClientOptions::MaxSessionRefreshTries] ?? 5;
    }

    /**
     * Sets up a new HTTP client configured for the current auth session
     *
     * Because Guzzle clients are immutable and options such as base_uri cannot be changed after instantiation,
     * a new client is created each time one of those options must change.
     */
    protected function setupHttpClient() {
        $this->httpClient = call_user_func($this->httpClientFactory, [
            'base_uri' => $this->authClient->getRestUrl(),
            'headers' => [
                'BhRestToken' => $this->authClient->getRestToken(),
            ],
        ]);
    }

    /**
     * @param array $options
     * @throws Auth\Exception\BullhornAuthException
     */
    public function initiateSession(array $options = []) {
        $gotSession = false;
        $tries = 0;
        do {
            try {
                $this->authClient->initiateSession($options);
                $gotSession = true;
            }
            catch (ClientException $e) {
                ++$tries;
                if ($tries >= $this->maxSessionRefreshTries) {
                    throw $e;
                }
            }
        } while (!$gotSession);
        $this->setupHttpClient();
    }

    /**
     * @param array $options
     * @throws Auth\Exception\CreateSessionException
     * @throws Auth\Exception\RestLoginException
     * @throws Auth\Exception\InvalidRefreshTokenException
     */
    public function refreshSession(array $options = []) {
        $this->authClient->refreshSession($options);
        $this->setupHttpClient();
    }

    /**
     * Returns if the client's current REST session is valid
     * @return bool
     */
    public function sessionIsValid() {
        return $this->authClient->sessionIsValid();
    }

    /**
     * @param array $options
     * @throws Auth\Exception\BullhornAuthException
     * @throws Auth\Exception\CreateSessionException
     * @throws Auth\Exception\RestLoginException
     */
    public function refreshOrInitiateSession(array $options = []) {
        try {
            $this->refreshSession($options);
        }
        catch (InvalidRefreshTokenException $e) {
            $this->initiateSession($options);
        }
    }

    /**
     * Makes a raw request to the API
     * @param string $method
     * @param string $url
     * @param array $options
     * @return ResponseInterface
     * @throws HttpException
     */
    public function rawRequest(string $method, string $url, $options = []) {
        try {
            return $this->httpClient->request($method, $url, $options);
        }
        catch (GuzzleException $e) {
            throw new HttpException($e);
        }
    }

    /**
     * Creates a new entity
     * @param string $entityType
     * @param array $entityProps
     * @return mixed
     * @throws HttpException
     */
    public function createEntity(string $entityType, array $entityProps) {
        try {
            $response = $this->httpClient->put("entity/$entityType", [
                'body' => json_encode((object)$entityProps),
            ]);
            return json_decode($response->getBody()->getContents());
        }
        catch (GuzzleException $e) {
            throw new HttpException($e);
        }
    }

    /**
     * Delets an entity
     * @param string $entityType
     * @param int $entityId
     * @return mixed
     * @throws HttpException
     */
    public function deleteEntity(string $entityType, int $entityId) {
        try {
            $response = $this->httpClient->delete("entity/$entityType/$entityId");
            $body = $response->getBody()->getContents();
            return json_decode($body);
        }
        catch (GuzzleException $e) {
            throw new HttpException($e);
        }
    }

    /**
     * Fetches entities
     * @param string $entityType
     * @param array $entityIds
     * @param array $params
     * @return mixed
     * @throws HttpException
     */
    public function fetchEntities(string $entityType, array $entityIds, array $params = []) {
//        try {
            $joinedIds = implode(',', $entityIds);
            $response = $this->httpClient->get("entity/{$entityType}/{$joinedIds}", [
                'query' => $params
            ]);
            return json_decode($response->getBody()->getContents())->data;
//        }
//        catch (GuzzleException $e) {
//            throw new HttpException($e);
//        }
    }

    /**
     * Creates an event subscription
     * @param string $subscriptionName
     * @param array $entityTypes
     * @param array $eventTypes
     * @return mixed
     * @throws HttpException
     */
    public function createEventSubscription(string $subscriptionName, array $entityTypes, array $eventTypes) {
        try {
            $response = $this->httpClient->put("event/subscription/$subscriptionName", [
                'query' => [
                    'type' => 'entity',
                    'names' => implode(',', $entityTypes),
                    'eventTypes' => implode(',', $eventTypes)
                ],
            ]);
            return json_decode($response->getBody()->getContents());
        }
        catch (GuzzleException $e) {
            throw new HttpException($e);
        }
    }

    /**
     * Deletes an event subscription
     * @param string $subscriptionName
     * @return mixed
     * @throws HttpException
     */
    public function deleteEventSubscription(string $subscriptionName) {
        try {
            $response = $this->httpClient->delete("event/subscription/$subscriptionName");
            return json_decode($response->getBody()->getContents());
        }
        catch (GuzzleException $e) {
            throw new HttpException($e);
        }
    }

    /**
     * Fetches events for an event subscription
     * @param string $subscriptionName
     * @param int $maxEvents
     * @param int|null $requestId
     * @return mixed
     * @throws HttpException
     */
    public function fetchEventSubscriptionEvents(string $subscriptionName, int $maxEvents = 100, ?int $requestId = null) {
        try {
            $response = $this->httpClient->get("event/subscription/$subscriptionName", [
                'query' => [
                    'maxEvents' => $maxEvents,
                    'requestId' => $requestId,
                ],
            ]);
            $body = $response->getBody()->getContents();
            return json_decode($body);
        }
        catch (GuzzleException $e) {
            throw new HttpException($e);
        }
    }
}
