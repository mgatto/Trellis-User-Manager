<?php

namespace Iplant\Command;

use Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Output\OutputInterface;

use EasyCSV\Reader as CsvReader;

use Iplant\Service\UserImporter\CsvUserImporter,
    Iplant\Service\UserImporter\JsonUserImporter,
    Iplant\Service\UserImporter\LdapUserImporter;

/**
 * Creates batches of users
 *
 * Currently, only CSV file inputs are accepted.
 *
 * Usage:
 * Use with the Symfony2 Console component
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
class BatchCreatorCommand extends Command {

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command.Command::configure()
     */
    function configure() {
        $this
            ->setName('users:create')
            ->setDescription('Batch create new users for iPlant')
            ->addArgument('type', InputArgument::REQUIRED, 'Input type for batch imports and creates')
            ->addArgument('file', InputArgument::REQUIRED, 'Data to be imported')
        ;
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command.Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        /* get arg */
        $file = $input->getArgument('file');
        $type = strtolower($input->getArgument('type'));

        $em = $this->_createEntityManager();

        /* select an importer */
        switch ( $type ) {
            /* we wrap each case with a test for file_exists instead of before
             * the switch because not every import type will be a physical file;
             * it could be a pipe from a service! */
            case 'csv':
                if ( file_exists($file) && is_readable($file) ) {

                    $reader = new CsvReader($file);
                    $importer = new CsvUserImporter($em, $reader->getHeaders());

                    while ($row = $reader->getRow()) {
                        try {
                            $output->writeln("<info>" . $importer->saveToTrellis($row) . "</info>");
                        } catch (\Exception $e) {
                            $output->writeln("<error>Error for user: '{$row['username']}'</error>");
                            $output->writeln($e->getMessage() . $e->getTraceAsString());
                        }
                    }
                } else {
                    // . __DIR__ . DIRECTORY_SEPARATOR . $file . doesn't work since __DIR__ is this class file's location, and not the calling script Scripts/trellis.php
                    throw new \RuntimeException("File {$file} does not exist or is not readable.");
                }

                break;

            case 'json':
                if ( file_exists($file) && is_readable($file) ) {

                    $importer = new JsonUserImporter($em);

                    $rows = json_decode(file_get_contents($file), true);
                    if (empty($rows)) {
                       $output->writeln("<error>Error: No JSON in file</error>");

                    } else {
                        foreach ( $rows as $row ) {
                             try {
                                $output->writeln("<info>" . $importer->saveToTrellis($row) . "</info>");
                            } catch (\Exception $e) {
                                $output->writeln("<error>Error for user: '{$row['person']['account']['username']}'</error>");
                                $output->writeln($e->getMessage() . $e->getTraceAsString());
                            }
                        }
                    }
                }

                break;

            case 'ldap':
                /* in this case, $file is really a possibly comma-delimited list of usernames */
                if ( ! empty($file) ) {

                    $importer = new LdapUserImporter($em);

                    $usernames = explode(',', $file);
                    foreach ( $usernames as $username ) {
                        try {
                            $output->writeln("<info>" . $importer->saveToTrellis($username) . "</info>");

                        } catch (\Exception $e) {
                            $output->writeln("<error>Error for user: '{$username}'</error>");
                            $output->writeln($e->getMessage() . $e->getTraceAsString());
                        }
                    }

                } else {
                    $output->writeln("<error>Error: No Usernames supplied</error>");
                }

                break;
        }
    }

    /**
     * Create a Doctrine Entity Manager
     *
     * An entity manager saves entity objects to the database
     *
     * @return Doctrine\ORM\EntityManager
     */
    private function _createEntityManager($options = null) {
        $config = new \Doctrine\ORM\Configuration();

        \Doctrine\Common\Annotations\AnnotationRegistry::registerFile('Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php');
        $reader = new \Doctrine\Common\Annotations\AnnotationReader();
        $reader->setDefaultAnnotationNamespace('Doctrine\ORM\Mapping\\');

        $reader->setIgnoreNotImportedAnnotations(true);
        $reader->setEnableParsePhpImports(false);

        $driver = new \Doctrine\ORM\Mapping\Driver\AnnotationDriver($reader, (array) realpath(__DIR__ . '/../Application/Models/Entities'));
        $chain = new \Doctrine\ORM\Mapping\Driver\DriverChain;
        $chain->addDriver($driver, 'Entities');

        //$driverImpl = $config->newDefaultAnnotationDriver(realpath(__DIR__ . '/../Application/Models/Entities'));
        $config->setMetadataDriverImpl($driver);
        $config->setMetadataCacheImpl(new \Doctrine\Common\Cache\ArrayCache);
        $config->setProxyDir(realpath(__DIR__ . '/../../../Application/Models/Proxies'));
        $config->setProxyNamespace('Proxies');
        $config->setAutoGenerateProxyClasses(false);

        $connectionOptions = array(
            'driver' => 'pdo_mysql',
            'dbname' => '',
            'user' => '',
            'host' => 'localhost',
            'password' => '',
        );

        /* DoctrineExtensions */
        $evm = new \Doctrine\Common\EventManager();
        $timestampableListener = new \Gedmo\Timestampable\TimestampableListener();
        $evm->addEventSubscriber($timestampableListener);

        /* Connect! */
        $em = \Doctrine\ORM\EntityManager::create($connectionOptions, $config, $evm);
        return $em;
    }
}
