<?php

declare(strict_types=1);

$databases['default']['default'] = [
    'database' => getenv('DRUPAL_DB_NAME') ?: 'rook_servicechannel',
    'username' => getenv('DRUPAL_DB_USER') ?: 'rook',
    'password' => getenv('DRUPAL_DB_PASSWORD') ?: 'rook',
    'prefix' => '',
    'host' => getenv('DRUPAL_DB_HOST') ?: 'db',
    'port' => getenv('DRUPAL_DB_PORT') ?: '3306',
    'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
    'driver' => getenv('DRUPAL_DB_DRIVER') ?: 'mysql',
];

$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: 'change-me-before-sharing';
$settings['config_sync_directory'] = '../configurations';
$settings['file_public_path'] = 'sites/default/files';
$settings['file_private_path'] = '../private';

$trusted_host_pattern = getenv('DRUPAL_TRUSTED_HOST_PATTERN');
if ($trusted_host_pattern) {
    $settings['trusted_host_patterns'][] = $trusted_host_pattern;
}

if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
    include $app_root . '/' . $site_path . '/settings.local.php';
}
