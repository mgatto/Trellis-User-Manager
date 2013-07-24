<?php

namespace Controllers;

use Silex\Application,
    Silex\ControllerCollection,
    Silex\ControllerProviderInterface;

class AboutController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = new ControllerCollection();

        /**  */
        $controllers->get('/', function() use ($app) {

            return $app['twig']->render(
                'about/index.html.twig', array()
            );
        });

        return $controllers;
    }
}
