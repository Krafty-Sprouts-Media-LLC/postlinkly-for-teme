<?php
/**
 * Filename: bootstrap.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Loads the development site's Composer autoloader (Pest,
 *              Illuminate, teme/contracts and postlinkly/core live there) and
 *              adds this package's own namespaces. Set POSTLINKLY_TEME_DEV to
 *              point at another Teme copy.
 *
 * @package Postlinkly
 * @since   1.0.0
 */

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

// The plugin keeps no vendor/ of its own: Teme reads every file in a plugin's
// folder when it loads it, and a dev vendor/ made each artisan call take
// half a minute (FINDINGS H3).
$temeDev = getenv('POSTLINKLY_TEME_DEV') ?: 'C:/Users/kings/Herd/postlinkly-teme';
$autoload = $temeDev.'/vendor/autoload.php';

if (! is_file($autoload)) {
    fwrite(STDERR, "Teme's vendor/autoload.php was not found at {$autoload}. Set POSTLINKLY_TEME_DEV.\n");
    exit(1);
}

/** @var ClassLoader $loader */
$loader = require $autoload;
$loader->addPsr4('Postlinkly\\Teme\\', dirname(__DIR__).'/src/');
$loader->addPsr4('Postlinkly\\Teme\\Tests\\', __DIR__.'/');
