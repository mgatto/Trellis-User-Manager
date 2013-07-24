<?php

namespace SilexProvider;

use Silex\Application;
use Silex\ServiceProviderInterface;

use Iplant\Service\Recaptcha;

/**
 * Used within Silex Controllers to use the recaptcha service
 *
 * @author mgatto
 *
 */
class RecaptchaServiceProvider implements ServiceProviderInterface
{
    /**
     * Register extension.
     *
     * @param  Application $app Application
     * @access public
     * @return void
     */
    public function register(Application $app)
    {
        $app['recaptcha'] = $app->share(function () use ($app) {
            if (!isset($app['recaptcha.private_key'])) {
                 throw new \Exception("The private key must be set");
            }

            $recaptcha = new Recaptcha(
                $app['request'],
                $app['recaptcha.private_key']
            );

            return $recaptcha;
        });
    }
}
