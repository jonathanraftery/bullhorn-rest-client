<?php namespace jonathanraftery\Bullhorn\Rest\Auth\Store;


use jonathanraftery\Bullhorn\Rest\Auth\Exception\DataStoreException;

/**
 * Class WordpressDataStore
 * Persists a session in Wordpress options
 * @package jonathanraftery\Bullhorn\Rest\Authentication
 */
class WordpressDataStore implements DataStoreInterface {
	const BH_OPTION_NAME = "bullhorn-datastore";

    /**
     * WordpressDataStore constructor.
     * @throws DataStoreException
     */
	function __construct() {
        if (!function_exists('update_option') || !function_exists('get_option')) {
            throw new DataStoreException('Wordpress data stored used outside of Wordpress context');
        }
    }

    function store(string $key, $value) {
        $data = $this->readDataFile();
        $data->tokens->$key = $value;
        \update_option(self::BH_OPTION_NAME, json_encode($data));
    }

    function get(string $key): ?string {
        $data = $this->readDataFile();
        if (isset($data->tokens->$key))
            return $data->tokens->$key;
        else
            return null;
    }

    private function readDataFile() {
	    $data = \get_option(self::BH_OPTION_NAME);
        if ($data)
            return json_decode($data);
        else
            return json_decode('{"tokens":{}}');
    }

}
