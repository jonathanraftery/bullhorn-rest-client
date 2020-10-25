<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/TestEnvKeys.php';

if (file_exists(__DIR__ . '/.env.test')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env.test');
    $dotenv->load();
}

if (!$_ENV[TestEnvKeys::BullhornClientId]
    || !$_ENV[TestEnvKeys::BullhornClientSecret]
    || !$_ENV[TestEnvKeys::BullhornUsername]
    || !$_ENV[TestEnvKeys::BullhornPassword]
) {
    throw new Exception('Set environment variables to run tests. See /test-util/.env.test.example');
}
