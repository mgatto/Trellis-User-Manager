<?php

/* Ensure library/ is on include_path */
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(__DIR__ . '/../Library'),
    get_include_path(),
)));

/* Define application environment */
defined('APPLICATION_ENV') || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ?: 'production'));

if ( APPLICATION_ENV !== 'production' ) {
    ini_set('log_errors_max_len', 2048);

    /** FirePHP debugging output as JSON for Firefox */
    require_once("phar://" . __DIR__ . "/../Library/firephp.phar/FirePHPCore/fb.php");

    /* start output buffering */
    ob_start();
}

/* ENV must be set before initializing Application() */
require __DIR__ . '/../Application/Application.php';

$app->run();
