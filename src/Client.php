<?php

namespace jonathanraftery\Bullhorn\REST;
use jonathanraftery\Bullhorn\REST\Authentication\Client as AuthClient;

class Client
{
    private $authClient;
    private $session;

    public function __construct($clientId, $clientSecret, $username, $password)
    {
        $this->authClient = new AuthClient(
            $clientId, $clientSecret, $username, $password
        );
        $this->session = $this->authClient->createSession();
    }
}
