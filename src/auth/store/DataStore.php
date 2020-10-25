<?php namespace jonathanraftery\Bullhorn\Rest\Authentication;

interface DataStore {
    public function store($key, $value);
    public function get($key);
}
