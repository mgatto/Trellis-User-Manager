<?php

namespace Controllers;

use Silex\Application,
    Silex\ControllerCollection,
    Silex\ControllerProviderInterface;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DashboardController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = new ControllerCollection();

        /**  */
        $controllers->get('/', function() use ($app) {
            /* Security! */
            if ( ! $app['auth']->hasIdentity() ) {
                throw new AccessDeniedHttpException();
            }

            return $app['twig']->render('dashboard/index.html.twig', array(
                'request_uri' => 'https://' . $_SERVER['SERVER_NAME'] . '/',
            ));

        })->requireHttps();;

        return $controllers;
    }
}
