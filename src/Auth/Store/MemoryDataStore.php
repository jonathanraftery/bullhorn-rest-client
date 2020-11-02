<?php namespace jonathanraftery\Bullhorn\Rest\Auth\Store;

/**
 * Class MemoryDataStore
 * Stores a session in memory (not for production use, as session will not persist across requests)
 * @package jonathanraftery\Bullhorn\Rest\Authentication
 */
class MemoryDataStore implements DataStoreInterface {
    private $data;

    public function store(string $key, $value) {
        $this->data[$key] = $value;
    }

    public function get(string $key): ?string {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }
}
