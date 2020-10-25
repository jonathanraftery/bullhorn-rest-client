<?php namespace jonathanraftery\Bullhorn\Rest\Authentication;

/**
 * Class LocalFileDataStore
 * Persists a session in a local file
 * @package jonathanraftery\Bullhorn\Rest\Authentication
 */
class LocalFileDataStore implements DataStore {
    const STORE_FILE_NAME = './data-store.json';

    public function store($key, $value)
    {
        $data = $this->readDataFile();
        $data->tokens->$key = $value;
        $this->saveData($data);
    }

    public function get($key)
    {
        $data = $this->readDataFile();
        if (isset($data->tokens->$key))
            return $data->tokens->$key;
        else
            return null;
    }

    private function readDataFile()
    {
        if (file_exists(self::STORE_FILE_NAME)) {
            $storeFile = fopen(self::STORE_FILE_NAME, 'r');
            $data = json_decode(file_get_contents(self::STORE_FILE_NAME));
            fclose($storeFile);
            return $data;
        }
        else
            return json_decode('{"tokens":{}}');
    }

    private function saveData($newData)
    {
        $storeFile = fopen(self::STORE_FILE_NAME, 'w');
        fwrite($storeFile, json_encode($newData));
        fclose($storeFile);
    }
}
