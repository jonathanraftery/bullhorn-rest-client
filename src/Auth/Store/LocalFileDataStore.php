<?php namespace jonathanraftery\Bullhorn\Rest\Auth\Store;

/**
 * Class LocalFileDataStore
 * Persists a session in a local file
 * @package jonathanraftery\Bullhorn\Rest\Authentication
 */
class LocalFileDataStore implements DataStoreInterface {
    private $filePath;

    public function __construct(string $filePath = './bullhorn-auth-store.json') {
        $this->filePath = $filePath;
    }

    public function store(string $key, $value)
    {
        $data = $this->readDataFile();
        $data->tokens->$key = $value;
        $this->saveData($data);
    }

    public function get(string $key): ?string
    {
        $data = $this->readDataFile();
        if (isset($data->tokens->$key))
            return $data->tokens->$key;
        else
            return null;
    }

    private function readDataFile()
    {
        if (file_exists($this->filePath)) {
            $storeFile = fopen($this->filePath, 'r');
            $data = json_decode(file_get_contents($this->filePath));
            fclose($storeFile);
            return $data;
        }
        else
            return json_decode('{"tokens":{}}');
    }

    private function saveData($newData)
    {
        $storeFile = fopen($this->filePath, 'w');
        fwrite($storeFile, json_encode($newData));
        fclose($storeFile);
    }
}
