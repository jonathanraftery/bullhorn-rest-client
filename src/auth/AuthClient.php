<?php namespace jonathanraftery\Bullhorn\Rest\Authentication;

use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Provider\GenericProvider as OAuth2Provider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class AuthClient
 * Creates and persists sessions with the Bullhorn REST API
 * @package jonathanraftery\Bullhorn\Rest\Authentication
 */
class AuthClient
{
    const AUTH_URL  = 'https://auth.bullhornstaffing.com/oauth/authorize';
    const TOKEN_URL = 'https://auth.bullhornstaffing.com/oauth/token';
    const LOGIN_URL = 'https://rest.bullhornstaffing.com/rest-services/login';

    private $clientId;
    private $authProvider;
    /** * @var DataStore */ private $dataStore;
    /** * @var GuzzleClientInterface */ private $httpClient;

    /**
     * AuthClient constructor.
     * @param string $clientId
     * @param string $clientSecret
     * @param array $collaborators
     */
    public function __construct(string $clientId, string $clientSecret, array $collaborators = [])
    {
        $this->clientId = $clientId;

        $dataStore = $collaborators[AuthClientCollaboratorKey::DataStore];
        $this->dataStore = $dataStore  ? $dataStore : new LocalFileDataStore();

        $httpClient = $collaborators[AuthClientCollaboratorKey::HttpClient];
        $this->httpClient = $httpClient ? $httpClient : new GuzzleClient();
        $this->authProvider = new OAuth2Provider(
            [
                'clientId' => $clientId,
                'clientSecret' => $clientSecret,
                'urlAuthorize' => static::AUTH_URL,
                'urlAccessToken' => static::TOKEN_URL,
                'urlResourceOwnerDetails' => null,
            ], [
                'httpClient' => $this->httpClient,
            ]
        );
    }

    public function getRestToken()
    { return $this->dataStore->get($this->getRestTokenKey()); }

    public function getRestUrl()
    { return $this->dataStore->get($this->getRestUrlKey()); }

    public function getRefreshToken()
    { return $this->dataStore->get($this->getRefreshTokenKey()); }

    private function getRestTokenKey()
    { return $this->clientId.'-restToken'; }

    private function getRestUrlKey()
    { return $this->clientId.'-restUrl'; }

    private function getRefreshTokenKey()
    { return $this->clientId.'-refreshToken'; }

    private function storeData($name, $value)
    { $this->dataStore->store($name, $value); }

    /**
     * Initiates a REST session
     * @param $username
     * @param $password
     * @param array $options
     * @throws BullhornAuthException
     */
    public function initiateSession($username, $password, $options = [])
    {
        $authCode = $this->fetchAuthorizationCode(
            $username,
            $password
        );
        $accessToken = $this->createAccessToken($authCode);
        $session = $this->createSession(
            $accessToken,
            $options
        );
        $this->storeSession($session);
    }

    /**
     * Refreshes the current REST session
     * @param array $options
     * @throws CreateSessionException
     * @throws InvalidRefreshTokenException
     * @throws RestLoginException
     */
    public function refreshSession($options = [])
    {
        $refreshToken = $this->dataStore->get(
            $this->getRefreshTokenKey()
        );
        $accessToken = $this->refreshAccessToken($refreshToken);
        $session = $this->createSession(
            $accessToken,
            $options
        );
        $this->storeSession($session);
    }

    /**
     * Returns if the current REST sessions is valid
     * @return bool
     */
    public function sessionIsValid()
    {
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
    private function createSession($accessToken, $options)
    {
        $response = $this->doRestLogin($accessToken->getToken(), $options);
        if ($response->getStatusCode() == 200) {
            $this->storeData(
                $this->getRefreshTokenKey(),
                $accessToken->getRefreshToken()
            );
            return json_decode($response->getBody());
        }
        else {
            throw new CreateSessionException();
        }
    }

    private function storeSession($session)
    {
        $this->storeData(
            $this->getRestTokenKey(),
            $session->BhRestToken
        );
        $this->storeData(
            $this->getRestUrlKey(),
            $session->restUrl
        );
    }

    /**
     * Sends a login request to the Bullhorn API
     * @param $accessToken
     * @param $options
     * @return ResponseInterface
     * @throws RestLoginException
     */
    private function doRestLogin($accessToken, $options)
    {
        try {
            $options = array_merge(
                $this->getDefaultSessionOptions(),
                $options
            );
            $options['access_token'] = $accessToken;

            $fullUrl = static::LOGIN_URL . '?' . http_build_query($options);
            $loginRequest = $this->authProvider->getAuthenticatedRequest(
                'GET',
                $fullUrl,
                $accessToken
            );
            return $this->authProvider->getResponse($loginRequest);
        }
        catch (Exception $e) {
            throw new RestLoginException($e->getMessage());
        }
    }

    private function getDefaultSessionOptions()
    {
        return [
            'version' => '*',
            'ttl' => 60
        ];
    }

    /**
     * Creates an access token for the OAuth provider
     * @param $authCode
     * @return AccessToken|AccessTokenInterface
     * @throws InvalidClientSecretException|InvalidAuthCodeException|BullhornAuthException
     */
    private function createAccessToken($authCode)
    {
        try {
            return $this->authProvider->getAccessToken(
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
    private function refreshAccessToken($refreshToken)
    {
        try {
            return $this->authProvider->getAccessToken(
                OauthGrantTypes::RefreshToken,
                ['refresh_token' => $refreshToken]
            );
        } catch (IdentityProviderException $e) {
            throw new InvalidRefreshTokenException('Attempted session refresh with invalid refresh token');
        }
    }

    /**
     * Fetches an auth code from the OAuth provider
     * @param $username
     * @param $password
     * @return string|null
     * @throws BullhornAuthException
     */
    private function fetchAuthorizationCode($username, $password) : ?string
    {
        try {
            $authRequest = $this->authProvider->getAuthorizationUrl([
                'response_type' => OauthResponseTypes::AuthorizationCode,
                'action' => OauthActions::Login,
                'username' => $username,
                'password' => $password
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
            throw new BullhornAuthException("Failed to fetch authorization code (HTTP error: ${errorMessage})");
        }
    }
}
