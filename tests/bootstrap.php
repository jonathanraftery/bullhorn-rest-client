<?php

require __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/.env.test')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env.test');
    $dotenv->load();
}
