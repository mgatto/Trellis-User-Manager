<?php

namespace SilexProvider;

use Silex\Application;
use Silex\ServiceProviderInterface;

/**
 * Used within Silex Controllers to filter inputs
 *
 * @author mgatto
 *
 */
class FilterServiceProvider implements ServiceProviderInterface
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
        //Depends on Zend_Filter* being autoloadable
        /* I've only gotten this to work by appending the ZF Library to
         * the classpath */
        if (! isset($app['zend'])) {

        }

        //Provide $app['filter']
        $app['filter'] = $app->protect(function ($filter, $args = null) use ($app) {
            $class  = '\Zend_Filter_' . ucfirst($filter);

            /* Zend_Filter_Input has serious issues with traversing arrays,
             * so we get an Array Filter from:
             * framework.zend.com/svn/framework/laboratory/library/Zend/Filter/Array.php
             */
            $filterer = new \Zend_Filter_Array(new $class($args), true);

            return $filterer;
        });
    }
}
