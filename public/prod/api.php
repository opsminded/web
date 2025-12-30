<?php
/**
 * Production Environment API Entry Point
 *
 * This is a thin wrapper that includes the shared api.php
 * Environment-specific configuration is loaded from .env in this directory
 */

// Load autoloader first
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment-specific configuration BEFORE including shared api.php
// Config::load() has singleton protection, so this will be the config used
\Internet\Graph\Config::load(__DIR__ . '/.env');

// Include the shared API implementation
require_once __DIR__ . '/../api.php';
