<?php
/*
 * This file is part of the Symfony package.
 *
 * @author (c) Fabien Potencier <fabien@symfony.com>
 * @author Michael Gatto <mgatto@iplantcollaborative.org>
 *
 * mgatto: Use this in Silex only along with Symfony Components Beta5 or above.
 * DoctrineBridge was refactored after Beta4 to accept a Registry instead of
 * just the raw Doctrine EntityManager instance.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SilexProvider\Doctrine2ServiceProvider;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\ORMException;

/**
 * References all Doctrine connections and entity managers in a given Container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Registry implements RegistryInterface
{
    private $connection;
    private $entityManager;

    public function __construct($connection, $entityManager)
    {
        $this->connection = $connection;
        $this->entityManager = $entityManager;
    }

    /**
     * Gets the default connection name.
     *
     * @return string The default connection name
     */
    public function getDefaultConnectionName()
    {
        return $this->connection;
    }

    /**
     * Gets the named connection.
     *
     * @param string $name The connection name (null for the default one)
     *
     * @return Connection
     */
    public function getConnection($name = null)
    {
        return $this->connection;
    }

    /**
     * Gets an array of all registered connections
     *
     * @return array An array of Connection instances
     */
    public function getConnections()
    {
        return array($this->connection);
    }

    /**
     * Gets all connection names.
     *
     * @return array An array of connection names
     */
    public function getConnectionNames()
    {
        return array($this->connection);
    }

    /**
     * Gets the default entity manager name.
     *
     * @return string The default entity manager name
     */
    public function getDefaultEntityManagerName()
    {
        return $this->entityManager;
    }

    /**
     * Gets a named entity manager.
     *
     * @param string $name The entity manager name (null for the default one)
     *
     * @return EntityManager
     */
    public function getEntityManager($name = null)
    {
        return $this->entityManager;
    }

    /**
     * Gets an array of all registered entity managers
     *
     * @return array An array of EntityManager instances
     */
    public function getEntityManagers()
    {
        return array($this->entityManager);
    }

    /**
     * Resets a named entity manager.
     *
     * This method is useful when an entity manager has been closed
     * because of a rollbacked transaction AND when you think that
     * it makes sense to get a new one to replace the closed one.
     *
     * Be warned that you will get a brand new entity manager as
     * the existing one is not useable anymore. This means that any
     * other object with a dependency on this entity manager will
     * hold an obsolete reference. You can inject the registry instead
     * to avoid this problem.
     *
     * @param string $name The entity manager name (null for the default one)
     *
     * @return EntityManager
     */
    public function resetEntityManager($name = null)
    {
        return $this->entityManager;
    }

    /**
     * Resolves a registered namespace alias to the full namespace.
     *
     * This method looks for the alias in all registered entity managers.
     *
     * @param string $alias The alias
     *
     * @return string The full namespace
     *
     * @see Configuration::getEntityNamespace
     */
    public function getEntityNamespace($alias)
    {
        try {
                return $this->getEntityManager()->getConfiguration()->getEntityNamespace($alias);
            } catch (ORMException $e) {

            }

        throw ORMException::unknownEntityNamespace($alias);
    }

    /**
     * Gets all connection names.
     *
     * @return array An array of connection names
     */
    public function getEntityManagerNames()
    {
        return array(0);
    }

    /**
     * Gets the EntityRepository for an entity.
     *
     * @param string $entityName        The name of the entity.
     * @param string $entityManagerNAme The entity manager name (null for the default one)
     *
     * @return Doctrine\ORM\EntityRepository
     */
    public function getRepository($entityName, $entityManagerName = null)
    {
        return $this->getEntityManager()->getRepository($entityName);
    }

    /**
     * Gets the entity manager associated with a given class.
     *
     * @param string $class A Doctrine Entity class name
     *
     * @return EntityManager|null
     */
    public function getEntityManagerForClass($class) {
        return $this->getEntityManager();
    }
}
