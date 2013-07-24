<?php

namespace SilexProvider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use SilexProvider\ConfigServiceProvider\Config;

class ConfigServiceProvider implements ServiceProviderInterface {

    public function register(Application $app) {

        // Config service provider cannot work if it does not where
        // the files are stored, and what's the current environment.
        if (!isset($app['config.env']) || !isset($app['config.path'])) {
            die('$app[\'config.env\'] or $app[\'config.path\'] property not set.');
        }

        // If no common filename is provided, we default to an empty string.
        // The result is that no common file will be used.
        if (!isset($app['config.common.filename'])) {
            $app['config.common.filename'] = '';
        }

        // Default prefix is _config unless stated otherwise.
        if (!isset($app['config.prefix'])) {
            $app['config.prefix'] = 'config_';
        }

        // Returning the config object.
        $app['config'] = $app->share(function () use ($app) {
                    return new Config($app['config.path'], $app['config.env'], $app['config.common.filename'], $app['config.prefix']);
                });
    }

}
