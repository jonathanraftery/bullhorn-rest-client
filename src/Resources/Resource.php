<?php

namespace jonathanraftery\Bullhorn\Rest\Resources;

abstract class Resource
{
    protected $restClient;

    function __construct($restClient)
    {
        $this->restClient = $restClient;
    }
}
