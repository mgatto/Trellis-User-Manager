<?php

namespace SilexProvider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use SilexProvider\AuthServiceProvider\Storage;
use Iplant\Auth\Adapter\BasicAuthAdapter;
use Iplant\Auth\Adapter\CasAdapter;
use Iplant\Auth\Adapter\DummyAdapter;

/**
 * Provides an instance of Zend_Auth
 *
 * Uses a custom storage class for use with Zend_Auth using Symfony2's
 * Session instead of Zend_Session.
 *
 * Usage:
 * <code>
 * $app->register(new SilexExtension\AuthExtension, array(
 * 	  'zend.class_path' => '',
 *    'auth.login.url' => '/security/login',
 *    'auth.login.message' => '',
 *    'auth.session' => $app['session'],
 * ));
 * </code>
 *
 * PHP version 5
 *
 * @category  project_name
 * @package   package_name
 * @author    Michael Gatto <mgatto@lisantra.com>
 * @copyright 2011 Iplant Collaborative
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link
 * @filesource
 */
class AuthServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
            $app['auth'] = $app->share(function () use ($app) {

            /** Set options */
            if (isset($app['zend.class_path'])) {
                $app['autoloader']->registerPrefix('Zend_', $app['zend.class_path']);
            }

            $auth = \Zend_Auth::getInstance();

            /* We must use a custom storage class to interface with Symfony2's
             * Session class, since Zend_Session is largely unable to operate
             * when another Session handler is active, and we're already using
             * Symfony2's from Silex\SessionExtension. */
            $auth->setStorage(new Storage($app['session']));

            return $auth;
        });

        $app['auth.adapter'] = $app->share(function () use ($app) {
            /** Choose Adapter */

            //$app['request']->get('_route');
            $route = $app['routes']->get($app['request']->get('_route'));
            $auth = $route->getDefault('auth');

            switch ( true ) {
                case ( 'basic' === $auth ):
                    $adapter = new BasicAuthAdapter($app['request'], $app['doctrine.orm.em']);
                    break;

                case ( ($app['debug']) && ('basic' !== $auth) ):
                    $adapter = new DummyAdapter();
                    break;

                case ( empty($auth) ) :
                default:
                    /* The adapter has many default options; no need to merge them here */
                    $adapter = new CasAdapter($app['auth.configuration'], $app['doctrine.orm.em']);
                    break;
            }

            return $adapter;
        });
    }
}
