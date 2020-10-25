<?php namespace jonathanraftery\Bullhorn\Rest\Authentication;

/**
 * Class MemoryDataStore
 * Stores a session in memory (not for production use, as session will not persist across requests)
 * @package jonathanraftery\Bullhorn\Rest\Authentication
 */
class MemoryDataStore implements DataStore {
    private $data;

    public function store($key, $value) {
        $this->data[$key] = $value;
    }

    public function get($key) {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }
}
