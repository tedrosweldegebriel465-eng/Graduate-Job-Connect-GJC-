<?php
/**
 * PHPUnit bootstrap — sets up autoloader and constants for tests
 */

declare(strict_types=1);

// Load project autoloader (no Composer needed)
require_once dirname(__DIR__) . '/config/bootstrap.php';

// Test-specific overrides
if (!defined('BCRYPT_COST')) define('BCRYPT_COST', 4); // Fast hashing in tests
if (!defined('APP_ENV'))     define('APP_ENV', 'testing');
