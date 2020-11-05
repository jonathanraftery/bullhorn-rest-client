<?php namespace jonathanraftery\Bullhorn\Rest;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;
use GuzzleHttp\Client as GuzzleClient;
use jonathanraftery\Bullhorn\Rest\Auth\AuthClient;
use jonathanraftery\Bullhorn\Rest\Auth\AuthClientInterface;
use jonathanraftery\Bullhorn\Rest\Auth\AuthClientOptions;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidRefreshTokenException;
use jonathanraftery\Bullhorn\Rest\Exception\HttpException;
use jonathanraftery\Bullhorn\Rest\Exception\InvalidConfigException;
use jonathanraftery\Bullhorn\Rest\Exception\InvalidTokenException;
use Psr\Http\Message\RequestInterface;
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
    /** @var HandlerStack */ protected $httpHandlerStack;
    protected $shouldAutoRefreshSessions;
    protected $maxSessionRefreshTries;

    /**
     * Client constructor.
     * @param array $options
     * @throws InvalidConfigException
     * @throws Auth\Exception\InvalidConfigException
     * @throws Auth\Exception\BullhornAuthException
     */
    function __construct(array $options = []) {
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
            : function ($httpOptions) {return new GuzzleClient($httpOptions);}
        ;

        $this->shouldAutoRefreshSessions = $options[ClientOptions::AutoRefreshSessions] ?? true;
        $this->maxSessionRefreshTries = $options[ClientOptions::MaxSessionRefreshTries] ?? 5;

        $this->setupHttpHandlerStack();
        $this->setupHttpClient();
    }

    /**
     * Sets up middleware for HTTP requests
     */
    protected function setupHttpHandlerStack() {
        $this->httpHandlerStack = HandlerStack::create(new CurlHandler());

        // adds session initialization if requests made when no session is active
        $this->httpHandlerStack->push(function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                if ($this->sessionIsValid()) {
                    return $handler($request, $options);
                }
                else {
                    $this->refreshOrInitiateSession();
                    $requestPath = str_replace($this->authClient->getRestUrl(), '', $request->getUri());
                    $refreshedRequest = $request
                        ->withHeader('BhRestToken', $this->authClient->getRestToken())
                        ->withUri(new Uri($this->authClient->getRestUrl() . $requestPath))
                    ;
                    return $handler($refreshedRequest, $options);
                }
            };
        });

        // adds error handling and session refresh middleware to HTTP handler
        $this->httpHandlerStack->push(function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($handler, $request, $options) {
                        if ($response->getStatusCode() === 401) {
                            $body = json_decode($response->getBody()->getContents());
                            if ($body->errorMessageKey === 'errors.authentication.invalidRestToken') {
                                if ($this->shouldAutoRefreshSessions) {
                                    $requestPath = str_replace($this->authClient->getRestUrl(), '', $request->getUri());
                                    $this->refreshOrInitiateSession();
                                    $refreshedRequest = $request
                                        ->withHeader('BhRestToken', $this->authClient->getRestToken())
                                        ->withUri(new Uri($this->authClient->getRestUrl() . $requestPath))
                                    ;
                                    return $handler($refreshedRequest, $options);
                                }
                                else {
                                    throw new InvalidTokenException();
                                }
                            }
                            $response->getBody()->rewind();
                        }
                        return $response;
                    }
                );
            };
        });
    }

    /**
     * Sets up a new HTTP client configured for the current auth session
     *
     * Because Guzzle clients are immutable and options such as base_uri cannot be changed after instantiation,
     * a new client is created each time one of those options must change.
     */
    protected function setupHttpClient() {
        $this->httpClient = call_user_func($this->httpClientFactory, [
            'handler' => $this->httpHandlerStack,
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
    function initiateSession(array $options = []) {
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
    function refreshSession(array $options = []) {
        $this->authClient->refreshSession($options);
        $this->setupHttpClient();
    }

    /**
     * Returns if the client's current REST session is valid
     * @return bool
     */
    function sessionIsValid() {
        return $this->authClient->sessionIsValid();
    }

    /**
     * @param array $options
     * @throws Auth\Exception\BullhornAuthException
     * @throws Auth\Exception\CreateSessionException
     * @throws Auth\Exception\RestLoginException
     */
    function refreshOrInitiateSession(array $options = []) {
        try {
            $this->refreshSession($options);
        }
        catch (InvalidRefreshTokenException $e) {
            $this->initiateSession($options);
        }
    }

    /**
     * Gets the current REST token
     * @return string|null
     */
    function getRestToken(): ?string {
        return $this->authClient->getRestToken();
    }

    /**
     * Gets the current REST URL
     * @return string|null
     */
    function getRestUrl(): ?string {
        return $this->authClient->getRestUrl();
    }

    /**
     * Makes a raw request to the API
     * @param string $method
     * @param string $url
     * @param array $options
     * @return ResponseInterface
     * @throws GuzzleException
     */
    function rawRequest(string $method, string $url, $options = []) {
        return $this->httpClient->request($method, $url, $options);
    }

    /**
     * Creates a new entity
     * @param string $entityType
     * @param array $entityProps
     * @return mixed
     * @throws HttpException
     */
    function createEntity(string $entityType, array $entityProps) {
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
     * Deletes an entity
     * @param string $entityType
     * @param int $entityId
     * @return mixed
     * @throws HttpException
     */
    function deleteEntity(string $entityType, int $entityId) {
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
    function fetchEntities(string $entityType, array $entityIds, array $params = []) {
        try {
            $joinedIds = implode(',', $entityIds);
            $response = $this->httpClient->get("entity/{$entityType}/{$joinedIds}", [
                'query' => $params
            ]);
            return json_decode($response->getBody()->getContents())->data;
        }
        catch (GuzzleException $e) {
            throw new HttpException($e);
        }
    }

    /**
     * Searches entities
     * @param string $entityType
     * @param string $luceneQuery
     * @param array $params
     * @return mixed
     * @throws HttpException
     */
    function searchEntities(string $entityType, string $luceneQuery, array $params = []) {
        try {
            $response = $this->httpClient->get("search/$entityType", [
                'query' => array_merge($params, ['query' => $luceneQuery]),
            ]);
            $response->getBody()->rewind();
            return json_decode($response->getBody()->getContents());
        } catch (GuzzleException $e) {
            throw new HttpException($e);
        }
    }

    /**
     * Creates an event subscription
     * @param string $subscriptionName
     * @param array $entityTypes
     * @param array $eventTypes
     * @return mixed
     * @throws HttpException
     */
    function createEventSubscription(string $subscriptionName, array $entityTypes, array $eventTypes) {
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
    function deleteEventSubscription(string $subscriptionName) {
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
    function fetchEventSubscriptionEvents(string $subscriptionName, int $maxEvents = 100, ?int $requestId = null) {
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
