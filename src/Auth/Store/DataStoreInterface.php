<?php namespace jonathanraftery\Bullhorn\Rest\Auth\Store;

interface DataStoreInterface {
    public function store(string $key, $value);
    public function get(string $key): ?string;
}
