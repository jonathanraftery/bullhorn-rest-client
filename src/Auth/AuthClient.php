<?php namespace jonathanraftery\Bullhorn\Rest\Auth;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use jonathanraftery\Bullhorn\Rest\Auth\CredentialsProvider\CredentialsProviderInterface;
use jonathanraftery\Bullhorn\Rest\Auth\CredentialsProvider\EnvironmentCredentialsProvider;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\BullhornAuthException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\CreateSessionException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidAuthCodeException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidClientIdException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidClientSecretException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidConfigException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidRefreshTokenException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\InvalidUserCredentialsException;
use jonathanraftery\Bullhorn\Rest\Auth\Exception\RestLoginException;
use jonathanraftery\Bullhorn\Rest\Auth\Oauth\OauthActions;
use jonathanraftery\Bullhorn\Rest\Auth\Oauth\OauthGrantTypes;
use jonathanraftery\Bullhorn\Rest\Auth\Oauth\OauthResponseTypes;
use jonathanraftery\Bullhorn\Rest\Auth\Store\DataStoreInterface;
use jonathanraftery\Bullhorn\Rest\Auth\Store\LocalFileDataStore;
use League\OAuth2\Client\Provider\GenericProvider as OAuth2Provider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Creates and persists sessions with the Bullhorn REST API
 * @package jonathanraftery\Bullhorn\Rest\Authentication
 */
class AuthClient implements AuthClientInterface {
    const AUTH_URL  = 'https://auth.bullhornstaffing.com/oauth/authorize';
    const TOKEN_URL = 'https://auth.bullhornstaffing.com/oauth/token';
    const LOGIN_URL = 'https://rest.bullhornstaffing.com/rest-services/login';

    /** @var CredentialsProviderInterface */ protected $credentialsProvider;
    /** @var DataStoreInterface */ protected $dataStore;
    /** @var GuzzleClientInterface */ protected $httpClient;
    /** @var OAuth2Provider */ protected $oauthProvider;
    protected $restTokenStorageKey = '{{clientId}}-rest-token';
    protected $restUrlStorageKey = '{{clientId}}-rest-url';
    protected $refreshTokenStorageKey = '{{clientId}}-refresh-token';

    /**
     * AuthClient constructor.
     * @param array $options
     * @throws InvalidConfigException
     */
    public function __construct(array $options = []) {
        $this->credentialsProvider = array_key_exists(AuthClientOptions::CredentialsProvider, $options)
            ? $options[AuthClientOptions::CredentialsProvider]
            : new EnvironmentCredentialsProvider()
        ;
        if (!$this->credentialsProvider instanceof CredentialsProviderInterface) {
            throw new InvalidConfigException(AuthClientOptions::CredentialsProvider . ' must implement '. CredentialsProviderInterface::class);
        }

        if (array_key_exists(AuthClientOptions::RestTokenStorageKey, $options)) {
            $this->restTokenStorageKey = $options[AuthClientOptions::RestTokenStorageKey];
        }
        if (array_key_exists(AuthClientOptions::RestUrlStorageKey, $options)) {
            $this->restUrlStorageKey = $options[AuthClientOptions::RestUrlStorageKey];
        }
        if (array_key_exists(AuthClientOptions::RefreshTokenStorageKey, $options)) {
            $this->refreshTokenStorageKey = $options[AuthClientOptions::RefreshTokenStorageKey];
        }

        $this->dataStore = array_key_exists(AuthClientOptions::DataStore, $options)
            ? $options[AuthClientOptions::DataStore]
            : new LocalFileDataStore()
        ;
        if (!$this->dataStore instanceof DataStoreInterface) {
            throw new InvalidConfigException(AuthClientOptions::DataStore . ' must implement ' . DataStoreInterface::class);
        }

        $this->httpClient = array_key_exists(AuthClientOptions::HttpClient, $options)
            ? $options[AuthClientOptions::HttpClient]
            : new GuzzleClient()
        ;
        if (!$this->httpClient instanceof GuzzleClient) {
            throw new InvalidConfigException(AuthClientOptions::HttpClient . ' must be a ' . GuzzleClient::class);
        }

        $this->oauthProvider = new OAuth2Provider(
            [
                'clientId' => $this->credentialsProvider->getClientId(),
                'clientSecret' => $this->credentialsProvider->getClientSecret(),
                'urlAuthorize' => static::AUTH_URL,
                'urlAccessToken' => static::TOKEN_URL,
                'urlResourceOwnerDetails' => null,
            ], [
                'httpClient' => $this->httpClient,
            ]
        );
    }

    public function getRestToken(): ?string {
        return $this->dataStore->get($this->getRestTokenKey());
    }

    public function getRestUrl(): ?string {
        return $this->dataStore->get($this->getRestUrlKey());
    }

    public function getRefreshToken(): ?string {
        return $this->dataStore->get($this->getRefreshTokenKey());
    }

    private function getRestTokenKey(): string {
        return str_replace('{{clientId}}', $this->credentialsProvider->getClientId(), $this->restTokenStorageKey);
    }

    private function getRestUrlKey(): string {
        return str_replace('{{clientId}}', $this->credentialsProvider->getClientId(), $this->restUrlStorageKey);
    }

    private function getRefreshTokenKey(): string {
        return str_replace('{{clientId}}', $this->credentialsProvider->getClientId(), $this->refreshTokenStorageKey);
    }

    /**
     * Initiates a REST session
     * @param array $options
     * @throws BullhornAuthException
     */
    public function initiateSession(array $options = []) {
        $authCode = $this->fetchAuthorizationCode();
        $accessToken = $this->createAccessToken($authCode);
        $session = $this->createSession($accessToken, $options);
        $this->storeSession($session);
    }

    /**
     * Refreshes the current REST session
     * @param array $options
     * @throws CreateSessionException
     * @throws InvalidRefreshTokenException
     * @throws RestLoginException
     */
    public function refreshSession(array $options = []) {
        $refreshToken = $this->dataStore->get($this->getRefreshTokenKey());
        if (!$refreshToken) {
            throw new InvalidRefreshTokenException();
        }
        $accessToken = $this->refreshAccessToken($refreshToken);
        $session = $this->createSession($accessToken, $options);
        $this->storeSession($session);
    }

    /**
     * Returns if the current REST sessions is valid
     * @return bool
     */
    public function sessionIsValid(): bool {
        return (
            !empty($this->getRestToken())
            && !empty($this->getRestUrl())
        );
    }

    /**
     * Creates a new session with the Bullhorn API
     * @param $accessToken
     * @param $options
     * @return mixed
     * @throws CreateSessionException
     * @throws RestLoginException
     */
    private function createSession($accessToken, array $options = []) {
        $response = $this->doRestLogin($accessToken->getToken(), $options);
        if ($response->getStatusCode() == 200) {
            $this->dataStore->store($this->getRefreshTokenKey(), $accessToken->getRefreshToken());
            return json_decode($response->getBody());
        }
        else {
            throw new CreateSessionException();
        }
    }

    private function storeSession($session) {
        $this->dataStore->store($this->getRestTokenKey(), $session->BhRestToken);
        $this->dataStore->store($this->getRestUrlKey(), $session->restUrl);
    }

    /**
     * Sends a login request to the Bullhorn API
     * @param $accessToken
     * @param $options
     * @return ResponseInterface
     * @throws RestLoginException
     */
    private function doRestLogin(string $accessToken, array $options = []) {
        try {
            $options = array_merge(
                ['version' => '2.0', 'ttl' => 60],
                $options
            );
            $options['access_token'] = $accessToken;

            $fullUrl = static::LOGIN_URL . '?' . http_build_query($options);
            $loginRequest = $this->oauthProvider->getAuthenticatedRequest(
                'GET',
                $fullUrl,
                $accessToken
            );
            return $this->oauthProvider->getResponse($loginRequest);
        }
        catch (Exception $e) {
            throw new RestLoginException($e->getMessage());
        }
    }

    /**
     * Creates an access token for the OAuth provider
     * @param $authCode
     * @return AccessToken|AccessTokenInterface
     * @throws InvalidClientSecretException|InvalidAuthCodeException|BullhornAuthException
     */
    private function createAccessToken(string $authCode) {
        try {
            return $this->oauthProvider->getAccessToken(
                OauthGrantTypes::AuthorizationCode,
                ['code' => $authCode]
            );
        }
        catch (IdentityProviderException $e) {
            if ($e->getMessage() === 'invalid_client') {
                throw new InvalidClientSecretException();
            }
            else if ($e->getMessage() === 'invalid_grant') {
                throw new InvalidAuthCodeException();
            }
            else {
                throw new BullhornAuthException('Failed to fetch access token');
            }
        }
    }

    /**
     * Refreshes the current access token
     * @param $refreshToken
     * @return AccessToken|AccessTokenInterface
     * @throws InvalidRefreshTokenException
     */
    private function refreshAccessToken(string $refreshToken) {
        try {
            return $this->oauthProvider->getAccessToken(
                OauthGrantTypes::RefreshToken,
                ['refresh_token' => $refreshToken]
            );
        } catch (IdentityProviderException $e) {
            throw new InvalidRefreshTokenException('Attempted session refresh with invalid refresh token');
        }
    }

    /**
     * Fetches an auth code from the OAuth provider
     * @return string|null
     * @throws BullhornAuthException
     */
    private function fetchAuthorizationCode() : ?string {
        try {
            $authRequest = $this->oauthProvider->getAuthorizationUrl([
                'response_type' => OauthResponseTypes::AuthorizationCode,
                'action' => OauthActions::Login,
                'username' => $this->credentialsProvider->getUsername(),
                'password' => $this->credentialsProvider->getPassword()
            ]);
            $response = $this->httpClient->get(
                $authRequest,
                ['allow_redirects' => false]
            );
            $responseBody = $response->getBody()->getContents();

            if (strpos($responseBody, 'Invalid Client Id') !== false) {
                throw new InvalidClientIdException();
            } elseif (strpos($responseBody, '<p class="error">') !== false) {
                throw new InvalidUserCredentialsException();
            }

            $locationHeader = $response->getHeader('Location')[0];
            if (!$locationHeader) {
                throw new BullhornAuthException('Failed to fetch authorization code');
            }

            $authCode = preg_split("/code=/", $locationHeader);
            if (count($authCode) > 1) {
                $authCode = preg_split("/&/", $authCode[1]);
                return urldecode($authCode[0]);
            }
            else {
                throw new BullhornAuthException('Failed to fetch authorization code');
            }
        }
        catch (GuzzleException $e) {
            $errorMessage = $e->getMessage();
            throw new BullhornAuthException("Failed to fetch authorization code (HTTP error: ". $errorMessage .")");
        }
    }
}
