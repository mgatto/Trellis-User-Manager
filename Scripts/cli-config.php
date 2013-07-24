<?php

set_include_path(implode(PATH_SEPARATOR, array(
    realpath(__DIR__ . '/../Library'),
    get_include_path(),
)));

$classLoader = new \Doctrine\Common\ClassLoader('Entities',realpath(__DIR__ . '/../Application/Models'));
$classLoader->register();

$classLoader = new \Doctrine\Common\ClassLoader('Proxies',realpath(__DIR__ . '/../Application/Models'));
$classLoader->register();

$classLoader = new \Doctrine\Common\ClassLoader('Gedmo', realpath(__DIR__ . '/../Library/DoctrineExtension'));
$classLoader->register();

$classLoader = new \Doctrine\Common\ClassLoader('Symfony', realpath(__DIR__ . '/../Library'));
$classLoader->register();

$config = new \Doctrine\ORM\Configuration();

// new (D2.1) call to the AnnotationRegistry
\Doctrine\Common\Annotations\AnnotationRegistry::registerFile('Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php');
$reader = new \Doctrine\Common\Annotations\AnnotationReader();
$reader->setDefaultAnnotationNamespace('Doctrine\ORM\Mapping\\');
//$reader->setAnnotationNamespaceAlias('Doctrine\\ORM\\Mapping\\', 'orm');

// new (D2.1) code necessary starting here
$reader->setIgnoreNotImportedAnnotations(true);
$reader->setEnableParsePhpImports(false);

$driver = new \Doctrine\ORM\Mapping\Driver\AnnotationDriver($reader, (array) realpath(__DIR__ . '/../Application/Models/Entities'));
$chain = new \Doctrine\ORM\Mapping\Driver\DriverChain;
$chain->addDriver($driver, 'Entities');

//$driverImpl = $config->newDefaultAnnotationDriver(realpath(__DIR__ . '/../Application/Models/Entities'));
$config->setMetadataDriverImpl($driver);
$config->setMetadataCacheImpl(new \Doctrine\Common\Cache\ArrayCache);
$config->setProxyDir(realpath(__DIR__ . '/../Application/Models/Proxies'));
$config->setProxyNamespace('Proxies');
$config->setAutoGenerateProxyClasses(true);

$connectionOptions = array(
    'driver' => 'pdo_mysql',
    'dbname' => '',
    'host' => 'localhost',
    'user' => '',
    'password' => '',
);

//DoctrineExtensions
$evm = new \Doctrine\Common\EventManager();
$timestampableListener = new \Gedmo\Timestampable\TimestampableListener();
$evm->addEventSubscriber($timestampableListener);

$em = \Doctrine\ORM\EntityManager::create($connectionOptions, $config, $evm);

$helperSet = new \Symfony\Component\Console\Helper\HelperSet(array(
    'db' => new \Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper($em->getConnection()),
    'em' => new \Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper($em)
));
