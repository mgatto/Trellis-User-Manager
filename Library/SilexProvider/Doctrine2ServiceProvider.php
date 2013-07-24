<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SilexProvider;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Configuration as DBALConfiguration;
use Doctrine\DBAL\Event\Listeners\MysqlSessionInit;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Configuration as ORMConfiguration;
use Doctrine\ORM\Mapping\Driver\DriverChain;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Mapping\Driver\YamlDriver;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\ApcCache;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\EventManager;

use Silex\Application;
use Silex\ServiceProviderInterface;

class Doctrine2ServiceProvider implements ServiceProviderInterface
{

    /* "When using MySQL, you need to register the MySQLInitListener provided in
DBAL which will send the /SET NAMES utf8/ query just after the
connection, due to the crappy handling of encoding in MySQL: both your
application and your database are configured in utf8 but if you are not
sending this query, the connection between them will convert the data to
Latin1, meaning that the utf8 field in the database will not contain
utf8 data."
 */


    public function register(Application $app)
    {
        // @TODO should we throw an Exception if orm is activated but not dbal ?
        if (isset($app['doctrine.dbal.connection_options'])) {
            $this->loadDoctrineConfiguration($app);
            $this->loadDoctrineDbal($app);
            if (isset($app['doctrine.orm']) and true === $app['doctrine.orm']) {
                $this->setOrmDefaults($app);
                $this->loadDoctrineOrm($app);
            }
        }

        foreach(array('Common', 'DBAL', 'ORM') as $vendor) {
            $key = sprintf('doctrine.%s.class_path', strtolower($vendor));
            if (isset($app[$key])) {
                $app['autoloader']->registerNamespace(sprintf('Doctrine\%s', $vendor), $app[$key]);
            }
        }
    }

    private function loadDoctrineDbal(Application $app)
    {
        $app['doctrine.dbal.event_manager'] = $app->share(function() {
            $eventManager = new EventManager;

            return $eventManager;
        });

        $app['doctrine.dbal.connection'] = $app->share(function() use($app) {

            if (!isset($app['doctrine.dbal.connection_options'])) {
                throw new \InvalidArgumentException('The "doctrine.orm.connection_options" parameter must be defined');
            }
            $config = $app['doctrine.configuration'];
            $eventManager = $app['doctrine.dbal.event_manager'];

            /* ensure UTF8 */
            $eventManager->addEventSubscriber(new MysqlSessionInit('utf8', 'utf8_general_ci'));

            $conn = DriverManager::getConnection($app['doctrine.dbal.connection_options'], $config, $eventManager);

            return $conn;
        });
    }

    private function loadDoctrineOrm(Application $app)
    {
        $self = $this;
        $app['doctrine.orm.em'] = $app->share(function() use($self, $app) {

            $connection = $app['doctrine.dbal.connection'];
            $config = $app['doctrine.configuration'];

            //github.com/l3pp4rd/DoctrineExtensions/
            $app['autoloader']->registerNamespace('Gedmo',  __DIR__ . '/../DoctrineExtension');

            $timestampableListener = new \Gedmo\Timestampable\TimestampableListener();

            $app['doctrine.dbal.event_manager']->addEventSubscriber($timestampableListener);
            // now this event manager should be passed to entity manager constructor

            $em = EntityManager::create($connection, $config, $app['doctrine.dbal.event_manager']);

            return $em;
        });
    }

    private function setOrmDefaults(Application $app)
    {
        $defaults = array(
            'entities' => array(
                array('type' => 'annotation', 'path' => 'Entity', 'namespace' => 'Entity')
            ),
            'proxies_dir' => 'cache/doctrine/Proxy',
            'proxies_namespace' => 'DoctrineProxy',
            'auto_generate_proxies' => true,
        );
        foreach($defaults as $key => $value) {
            if (!isset($app['doctrine.orm.'.$key])) {
                $app['doctrine.orm.'.$key] = $value;
            }
        }
    }

    public function loadDoctrineConfiguration(Application $app)
    {
        $app['doctrine.configuration'] = $app->share(function() use($app) {

            if (isset($app['doctrine.orm']) and true === $app['doctrine.orm']) {
                $config = new ORMConfiguration();
                if (isset($app['debug']) and true == $app['debug'] ) {
                     $cache = new ArrayCache();
                } else {
                    $cache = new ApcCache();
                }
                $config->setMetadataCacheImpl($cache);
                $config->setQueryCacheImpl($cache);
                $config->setResultCacheImpl($cache);

                $chain = new DriverChain;
                foreach((array)$app['doctrine.orm.entities'] as $entity) {
                    switch($entity['type']) {
                        case 'annotation':
                            // new (D2.1) call to the AnnotationRegistry
                            \Doctrine\Common\Annotations\AnnotationRegistry::registerFile('Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php');

                            $reader = new AnnotationReader();
                            $reader->setDefaultAnnotationNamespace('Doctrine\ORM\Mapping\\');
                            // $reader->setAnnotationNamespaceAlias('Doctrine\\ORM\\Mapping\\', 'orm');
                            // new (D2.1) code necessary starting here
                            $reader->setIgnoreNotImportedAnnotations(true);
                            $reader->setEnableParsePhpImports(false);
                            //@TODO OPPPS! I redefined $reader here!
                            $cached_reader = new \Doctrine\Common\Annotations\CachedReader(
                                new \Doctrine\Common\Annotations\IndexedReader($reader), $cache
                            );

                            $driver = new AnnotationDriver($cached_reader, (array) $entity['path']);
                            $chain->addDriver($driver, $entity['namespace']);
                            break;
                        case 'yml':
                            $driver = new YamlDriver((array)$entity['path']);
                            $driver->setFileExtension('.yml');
                            $chain->addDriver($driver, $entity['namespace']);
                            break;
                        case 'xml':
                            $driver = new XmlDriver((array)$entity['path'], $entity['namespace']);
                            $driver->setFileExtension('.xml');
                            $chain->addDriver($driver, $entity['namespace']);
                            break;
                        default:
                            throw new \InvalidArgumentException(sprintf('"%s" is not a recognized driver', $entity['type']));
                            break;
                    }
                }
                $config->setMetadataDriverImpl($chain);

                $config->setProxyDir($app['doctrine.orm.proxies_dir']);
                $config->setProxyNamespace($app['doctrine.orm.proxies_namespace']);
                $config->setAutoGenerateProxyClasses($app['doctrine.orm.auto_generate_proxies']);
            }
            else {
                $config = new DBALConfiguration;
            }

            return $config;
        });
    }
}

