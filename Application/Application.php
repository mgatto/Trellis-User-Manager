<?php

/**  Bootstraping */
require_once __DIR__ . '/../Library/silex.phar';

use Silex\Application;
use Silex\ControllerCollection;

use Silex\Provider\TwigServiceProvider;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\SymfonyBridgesServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\SessionServiceProvider;

use Symfony\Component\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\ClassLoader\ApcUniversalClassLoader;

use Monolog\Processor\WebProcessor;
use Doctrine\Common\Cache\ArrayCache;
use Iplant\Service\Notifier;

use Entities\Account\DuplicateException;

/* Root object */
$app = new Application();

$app['debug'] = APPLICATION_ENV !== 'production';

/* override the default loader to use APC if on production */
if ( ! $app['debug'] ) {
    $app['autoloader'] = $app->share(function ($app) {
        return new ApcUniversalClassLoader(isset($app['autoloader.prefix']) ? $app['autoloader.prefix'] : '');
    });
}

/* register libraries with Php 5.3 namespacing standards */
$loader->registerNamespaces(array(
    'Controllers' 	=> __DIR__,
    'SilexProvider' => __DIR__ . '/../Library',
    'Symfony'		 => __DIR__ . '/../Library',
    'Iplant' 		=> __DIR__ . '/../Library',
    'Entities' 		=> __DIR__ . '/Models',
    'Repositories' 	=> __DIR__ . '/Models',
    'Guzzle' 		=> __DIR__ . '/../Library',
    'Monolog' 		=> __DIR__ . '/../Library',
    'Gedmo' 		=> __DIR__ . '/../Library/DoctrineExtension',
    'Doctrine' 		=> __DIR__ . '/../Library',
));
/* register libraries using the PEAR naming convention */
$loader->registerPrefixes(array(
    /* For Zend 1.x */
       'Zend_' => __DIR__ . '/../Library',
       'Twig_' => __DIR__ . '/../Library',
));


// Setup config service
$app->register(new SilexProvider\ConfigServiceProvider, array(
    'config.env' => APPLICATION_ENV,
    'config.path' => __DIR__ . '/../Configuration',
    'config.common.filename' => 'common',
    'config.prefix' => '',
));

/** Extensions  */
$app->register(new MonologServiceProvider(), array(
    'monolog.logfile'       => __DIR__ . '/../Logs/application.log',
    'monolog.class_path'    => __DIR__ . '/../Library',
    'monolog.name' 			=> 'User Portal',
    'monolog.level'			=> 200, //Monolog\Logger::INFO
));
$app['monolog']->pushProcessor(new WebProcessor());

$app->register(new SilexProvider\FilterServiceProvider());
$app->register(new SilexProvider\Doctrine2ServiceProvider, array(
    'doctrine.dbal.connection_options' => array(
        'driver' => 'pdo_mysql',
        'dbname' => $app['config']->get('database', 'name'),
        'host' => $app['config']->get('database', 'host'),
        'user' => $app['config']->get('database', 'user'),
        'password' => $app['config']->get('database', 'password'),
        'charset ' => 'UTF8',
    ),
    'doctrine.orm' => true,
    'doctrine.orm.entities' => array(
        array(
            'type' => 'annotation',
            'path' => realpath(__DIR__ . '/Models/Entities'),
            'namespace' => 'Entities',
        ),
    ),
    'doctrine.orm.proxies_namespace' => 'Proxies',
    'doctrine.orm.proxies_dir' => realpath(__DIR__ . '/Models/Proxies'),
    'doctrine.orm.auto_generate_proxies' => false,
    'doctrine.common.class_path' => __DIR__ . '/../Library',
    'doctrine.dbal.class_path'   => __DIR__ . '/../Library',
    'doctrine.orm.class_path'    => __DIR__ . '/../Library',
    //added by mgatto
    //'doctrine.orm.cache'		 => new ArrayCache(),
));
$app->register(new SessionServiceProvider(), array(
    'session.storage.options' => array(
        'name' => '_SESS',
        'path' => '/',
        'secure' => false, //We know what happens when we do that!
        'httponly' => true
    ),
));

/* should be before Twig */
$app->register(new UrlGeneratorServiceProvider());
$app->register(new SymfonyBridgesServiceProvider(), array(
    'symfony_bridges.class_path' => __DIR__ . '/../Library'
));

/* All 3 are grouped for Forms */
//@TODO figure out how to remove Translations since we don't need them
$app->register(new ValidatorServiceProvider(), array(
    'validator.class_path'    => __DIR__ . '/../Library',
));
$app->register(new TranslationServiceProvider(), array(
    'translation.class_path' => __DIR__ . '/../Library',
    'translator.messages'    => array()
));
$app->register(new SilexProvider\Form2ServiceProvider(), array(
    'form.class_path' => __DIR__ . '/../Library',
    'form.tmp_dir' => __DIR__ . '/../Temp/uploads',
    //'form.secret' => sha1(__DIR__ . 'Howzit, Haoli?'),
));

$app->register(new TwigServiceProvider(), array(
    'twig.path'       => __DIR__ . '/Views',
    'twig.class_path' => __DIR__ . '/../Library',
    'twig.options' => array(
        'cache' => __DIR__ . '/../Temp/cache',
        'debug' => (bool) $app['debug'],
        'auto_reload' => (bool) $app['debug'],
    ),
));

$app->register(new SilexProvider\RecaptchaServiceProvider(), array(
    /* for domain:user.iplantcollaborative.org */
    'recaptcha.public_key' => $app['config']->get('recaptcha', 'public'),
    'recaptcha.private_key' => $app['config']->get('recaptcha', 'private'),
));

/* Needed for the LoggedIn Twig Extension in the layout, even though no one
 * would usually be logged in in the Main controller */
$app->register(new SilexProvider\AuthServiceProvider(), array(
    'zend.class_path' =>  __DIR__ . '/../Library/',
    'auth.configuration' => array(
        'hostname' => $app['config']->get('cas', 'host'),
        'port' => 443,
        'path' => "cas/", //AKA: 'context'
        'service' => "https://{$_SERVER['SERVER_NAME']}/security/authenticate",
        'protocol' => "https",
        'serviceParam' => "service",
        'ticketParam' => "ticket",
        'validationParam' => "serviceValidate",
        'xmlNameSpace' => "http://www.yale.edu/tp/cas",
        'login.message' => 'You must login',
        'logoutUrl' => "https://{$app['config']->get('cas', 'host')}/cas/logout?service=http://{$_SERVER['SERVER_NAME']}/security/logged-out"
    ),
));

/** App definition */
$app->error(function (\Exception $e) use ($app) {
    switch ( true ) {
        /* This needs to be above HttpException, since technically it is an
         * instance of that since its a derived class */
        case ( $e instanceof AccessDeniedHttpException ):
            return $app->redirect($app['auth.adapter']->getLoginUrl());
            break;

        case ( $e instanceof HttpException ):
            $code = ($e->getStatusCode()) ? $e->getStatusCode() : 500;
            return new Response('We are sorry, but something went terribly wrong: ' . $e->getMessage(), $code);
            break;

        case ( $e instanceof NotFoundHttpException ):
            $message = ($e->getMessage()) ? $e->getMessage() : 'The requested page could not be found.';

            return new Response($message, 404);
            break;

        case ( $e instanceof PDOException ):
            /* This will be logged in php.log */
            $message = "Sorry; There was a database error.";
            break;

        case ( $e instanceof DuplicateException ):
            $message =
                $e->getMessage() .
                "<h4>Forgotten Password</h4>" .
                "<p>If you just forgot your password, then go ahead and change it here: <a href=\"/reset/request\">Change Password</a></p>";
            break;

        default:
            $message = $e->getMessage();
            break;
    }

    return $app['twig']->render('error/error.html.twig', array(
        'code' => $e->getCode(),
        'exception' => $e,
        'error_message' => $message,
    ));
});

/** Sub-applications: Must use LazyApplication() else routes will NOT work! */
/* User registration */
if ( $app['debug'] ) {
    $app->mount('/test', new Controllers\TestController());
}

/* Mount Services for AJAXy access */
$app->mount('/services', new Controllers\ServicesController());

/* Mount users for AJAXy access */
$app->mount('/users', new Controllers\UsersController());

/* Iplant Admin for Users and Services */
$app->mount('/register', new Controllers\RegistrationController());

/* Users' access to Account, Apps and Services */
$app->mount('/dashboard', new Controllers\DashboardController());

/* Mount Security controller */
$app->mount('/security', new Controllers\SecurityController());

/* Mount Reset Password controller */
$app->mount('/reset', new Controllers\ResetController());

/* Mount Contact controller */
$app->mount('/contact', new Controllers\ContactController());

/* Mount REST controller */
$app->mount('/api/v1', new Controllers\RestApiController());

/* Mount Workshops contoller */
$app->mount('/api/client', new Controllers\ClientController());

/* Mount scratch controller for quick snippet testing */
$app->mount('/about', new Controllers\AboutController());

/* Mount scratch controller for quick snippet testing */
$app->mount('/test', new Controllers\TestController());


/* Runs before all actions */
$app->before(function (Request $request) use ($app) {
    /** Custom extensions for only this controller */
    $app['twig']->addExtension(new Iplant\Twig\Extension\LoggedInAsExtension($app));

    /** Handle flash messages */
    $message = ($app['session']->hasFlash('message')) ? $app['session']->getFlash('message') : '';
    $app['twig']->addGlobal('message', $message);
});

/** ROUTES */
$app->get('/', function () use ($app) {
    return $app['twig']->render('index/home.html.twig', array(
        'reset_url' => $app['url_generator']->generate('request_reset', array(), true),
        'register_url' => $app['url_generator']->generate('show_registration', array(), true),
    ));
});

$app->get('/contact-us', function() use ($app) {
    return $app['twig']->render('index/contact.html.twig', array());
});

/* Runs after all actions */
$app->after(function (Request $request, Response $response) use ($app) {
    // tear down
});

/* /Public/index.php has "$app->run();" */
return $app;
