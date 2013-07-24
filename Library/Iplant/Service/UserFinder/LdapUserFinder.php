<?php

namespace Iplant\Service\UserFinder;

use Doctrine\ORM\EntityManager;
use Iplant\Service\UserFinder\AbstractUserFinder;

class LdapUserFinder extends AbstractUserFinder
{
    /**
     * A connection to LDAP
     *
     * @var resource
     */
    protected $ldap;

    /**
     *
     *
     * @var string
     */
    protected $dn = 'ou=People,dc=iplantcollaborative,dc=org';

    /**
     * The LDAP server host
     *
     * @var string
     */
    protected $host;

    /**
     * Class constructor
     *
     * @param void
     *
     * @return void
     */
    public function __construct($host)
    {
        $this->ldap = $this->connect($host);
    }

    /**
     * (non-PHPdoc)
     * @see Iplant\Service\UserImporter.AbstractUserImporter::saveToTrellis()
     */
    public function find(array $criterion) {

        /* lightweight validation */
        if ( 1 !== count($criterion) ) {
            throw new \LogicException("Search criterion was not single. Will not search for more than one criterion at a time.");
        }

        /* Search either by email or username */
        switch ( key($criterion) ) {
            case 'email':
                $email = trim(current($criterion));

                /* search! */
                if ( false === ($sr = ldap_search($this->ldap, $this->dn, "(mail={$email})")) ) {
                    throw new \RuntimeException("Searching for Email: '{$email}' in LDAP failed: " . ldap_error($this->ldap));
                }

                break;

            case 'username':
                $username = trim(current($criterion));

                /* search! */
                if ( false === ($sr = ldap_search($this->ldap, $this->dn, "(uid={$username})")) ) {
                    throw new \RuntimeException("Searching for User: '{$username}' in LDAP failed: " . ldap_error($this->ldap));
                }

                break;

            default:
                throw new \LogicException("I can only search by username and email.");
        }

        $entries = ldap_get_entries($this->ldap, $sr);

        /* get rid of fault-inducing data... */
        //unset($entries['count']);

        return $entries;
    }

    /**
     * establish LDAP connection
     *
     * @return return_type
     */
    protected function connect($host) {
        $ldap = ldap_connect($host);
        if (! $ldap) {
            throw new \RuntimeException(ldap_error($ldap));
        }

        /* binding is required! */
        if (! ldap_bind($ldap)) {
            throw new \RuntimeException(ldap_error($ldap));
        }

        return $ldap;
    }

    /**
     *
     * @return
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     *
     * @param $host string
     */
    public function setHost($host)
    {
        $this->host = $host;
    }
}
