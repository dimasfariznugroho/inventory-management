<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load environment variables for integration tests
if (file_exists(dirname(__DIR__) . '/.env')) {
    require_once dirname(__DIR__) . '/config/env.php';
    Config\Env::load(dirname(__DIR__) . '/.env');
}
