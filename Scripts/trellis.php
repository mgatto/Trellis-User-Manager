#!/usr/bin/env php
<?php
/**
 * CCommand Line Interface (CLI) to Trellis, iPlant User Management
 *
 * Usage:
 * <code>php -f trellis.php <command> [args]</code>
 *
 * PHP version 5.3
 *
 * @category  Trellis
 * @package   CLI Scripts
 * @author    Michael Gatto <mgatto@iplantcollaborative.org>
 * @copyright 2012 The iPlant Collaborative
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @filesource
 */
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Iplant\Command\BatchCreatorCommand;
use Iplant\Command\TestCommand;
use Symfony\Component\ClassLoader\UniversalClassLoader;

/* don't let this script timeout; kill it with kill -9 if its hanging */
set_time_limit(0);

/* Don't rely on php.ini or system PATH.
 * We need this because of BatchCreatorCommand::91
 *     \Doctrine\Common\Annotations\AnnotationRegistry::registerFile('Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php');
 *
 * notice the partial path; One might think that registerNamespace() below
 * would suffice, it seems not.
 */
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(__DIR__ . '/../Library'),
    get_include_path(),
)));

/** PHP5.3 style autoloading */
require_once __DIR__ . '/../Library/Symfony/Component/ClassLoader/UniversalClassLoader.php';
$loader = new UniversalClassLoader();
$loader->register();

/** For Symfony 2.x */
$loader->registerNamespace('Symfony', realpath(__DIR__ . '/../Library'));

/** For iPlant Library */
$loader->registerNamespace('Iplant', realpath(__DIR__ . '/../Library'));

/** For EasyCSV */
$loader->registerNamespace('EasyCSV', realpath(__DIR__ . '/../Library'));

/** For Doctrine 2.x ORM entities and repositories */
$loader->registerNamespace('Doctrine', realpath(__DIR__ . '/../Library'));
/* Gedmo provides the timestampable behavior for Doctrine */
$loader->registerNamespace('Gedmo', realpath(__DIR__ . '/../Library/DoctrineExtension'));
/* actual model classes */
$loader->registerNamespace('Entities', realpath(__DIR__ . '/../Application/Models'));
$loader->registerNamespace('Repositories', realpath(__DIR__ . '/../Application/Models'));
$loader->registerNamespace('Proxies', realpath(__DIR__ . '/../Application/Models'));

/** For Guzzle Webservice client Library */
$loader->registerNamespace('Guzzle', __DIR__ . '/../Library');

/** For Zend 1.x */
$loader->registerPrefix('Zend_', __DIR__ . '/../Library');

/** Inputs */
$input = new ArgvInput();
$env = $input->getParameterOption(array('--env', '-e'), getenv('SYMFONY_ENV') ?: 'dev');
$debug = getenv('SYMFONY_DEBUG') !== '0' && ! $input->hasParameterOption(array('--no-debug', '')) && $env !== 'prod';

$cli = new Application('User Management CLI', '1.1.0');
$cli->setCatchExceptions(true);

/** Available commands */
$cli->add(new BatchCreatorCommand());
$cli->add(new TestCommand());
$command = $cli->find('users:create');

$cli->run();

