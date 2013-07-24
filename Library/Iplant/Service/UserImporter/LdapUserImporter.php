<?php

namespace Iplant\Service\UserImporter;

use Doctrine\ORM\EntityManager;
use Iplant\Service\UserImporter\AbstractUserImporter;
use Iplant\Service\Exporter\LotusExporter;

class LdapUserImporter extends AbstractUserImporter
{
     /**
     * The Doctrine Entity Manager
     *
     * @var EntityManager
     */
    protected $em;

    /**
     * A connection to LDAP
     *
     * @var Zend_Ldap
     */
    protected $ldap;

    /**
     * The LDAP server host
     *
     * @var string
     */
    protected $host;

    /**
     * The final, imported person entitiy
     *
     * @var \Entities\Person
     */
    protected $person;

    /**
     * Class constructor
     *
     * @param EntityManager $em
     *
     * @return void
     */
    public function __construct(EntityManager $em, $host)
    {
        $this->em = $em;
        $this->ldap = $this->connect($host);
    }

    /**
     * (non-PHPdoc)
     * @see Iplant\Service\UserImporter.AbstractUserImporter::saveToTrellis()
     */
    public function saveToTrellis($user)
    {
        $username = trim($user);
        $message = "";
        $dn = '';

        /* search! */
        if ( false === $sr = ldap_search($this->ldap, $dn, "(uid={$username})") ) {
            throw new \Exception(
                "Searching for User: '{$username}' in LDAP failed: " . ldap_error($this->ldap)
            );
        }
        $entries = ldap_get_entries($this->ldap, $sr);

        /* ldap still returns entries even if the user is not found...arse.
         * Theoretically, we should never reach this point, since ResetController
         * uses LdapFinder to check for either username or email. */
        if ( $entries['count'] === 0 ) {
            throw new \Exception("Username not found for this password reset request.");
        }

        /* get rid of fault-inducing data...even bigger arse. */
        unset($entries['count']);

        /** Loop through them and add */
        foreach ( $entries as $entry) {
            try {
                $person = new \Entities\Person();
                $person->setFirstname($entry['givenname'][0]);
                $person->setLastname($entry['sn'][0]);
                $person->setNotes('Imported from LDAP and LOTUS');
                $person->setStatus('completed');

                /* this is because of Person's constructor which must create empty child objects */
                $emails = $person->getEmails();
                $email = $emails[0];
                $email->setEmail($entry['mail'][0]);
                $email->setNotes('Imported from LDAP and LOTUS');
                $email->setStatus('completed');

                $account = $person->getAccount();
                /* some haven't set their password yet! */
                if ( ! array_key_exists('userpassword', $entry) ) {
                    $interim_password = $account->createTempPassword();
                    $account->setPassword($interim_password, true);

                    $message .= "Set interim password '{$interim_password}' for user: " .
                        $entry['sn'][0] . ', ' .
                        $entry['givenname'][0] .
                        ' (' . $entry['uid'][0] . ')'
                    ;

                } else {
                    $account->setPassword($entry['userpassword'][0], false);
                }
                $account->setIsValidated(true);
                $account->setUsername($entry['uid'][0]);
                $account->setNotes('Imported from LDAP and LOTUS');
                $account->setStatus('completed');

                $profile = new \Entities\Profile();
                if ( array_key_exists('departmentnumber', $entry) ) {
                    $profile->setDepartment($entry['departmentnumber'][0]);
                }

                $profile->setStatus('completed');
                $person->setProfile($profile);

                /* save now in case LOTUS or services fails below... */
                $this->em->persist($person);
                /* actual DB write occurs in flush() */
                $this->em->flush();

            } catch (\PDOException $pe) {
                /* in PHP 5.4 we can do this: $code = PDO::errorInfo()[1]  yeah! */
                $pdo_error = $this->em->getConnection()->errorInfo(); // or \PDOStatement->errorInfo()

                if ( in_array($pdo_error[1], array('1022','1062','1052','1169')) ) {
                    $email = $person['emails'][0]['email'];

                    throw new DuplicateException("
                        <p>Possible duplicate: username '{$username}' and/or email address '{$email}' are already in Trellis.</p>
                        <p>The full error is: {$pdo_error[2]}</p>
                    ");
                    //<p>If this is you, please just <a href=\"/dashboard\">login</a>.</p>

                } else {
                    throw new \Exception(
                        "Database Error for user: {$person->getAccount()->getUsername()} :" . $pe->getMessage() . "\r\n" . $pdo_error[1] . ": " . $pdo_error[2]
                    );
                }

            /* if its a non PDO Exception, just bubble it up */
            } catch (\Exception $e) {
                throw $e;
            }

            /** Services which they already have */
            /* service in col 1; LDAP group in col 2 */
            $service_to_group = array(
                'Atmosphere' => '', //blanked out for GitHub!
                'DNA Subway' => '',
                'Discovery Environment' => '',
                'My-Plant' => '',
                'iPlant Data Store' => '',
                'Wiki' => '',
                'Integrated Breeding Platform' => '',
            );

            $groups_dn = '';
            $groups_sr = ldap_search($this->ldap, $groups_dn, "(memberuid={$username})", array('gidnumber','cn','displayname'));
            $groups = ldap_get_entries($this->ldap, $groups_sr);

            /* account for the person's primary group  */
            $primary_group_id = $entry['gidnumber'][0];
            $primary_group_sr = ldap_search($this->ldap, $groups_dn, "(gidnumber={$primary_group_id})", array('gidnumber','cn','displayname'));
            $primary_group = ldap_get_entries($this->ldap, $primary_group_sr);

            /* get rid of fault-inducing data... */
            unset($groups['count'], $primary_group['count']);

            /* its an array of an array, thus we need [0] */
            array_unshift($groups, $primary_group[0]);

            /* avoid pesky php warnings in my CLI */
            $username_services = array();

            /* Reduce duplicates! This will cause PDOException since it will
             * cause the add-services block below to attempt to add duplicate services */
            foreach ($groups as $group) {
                $group_cns[] = $group['cn'][0];
            }
            $groups = array_unique($group_cns);

            foreach ($groups as $group) {
                /* find the service which the LDAP group matches, if any */
                if ( $names = array_keys($service_to_group, $group) ) {
                    /* handle multiple positive finds */
                    foreach ($names as $name) {
                        /* get the service */
                        $service = $this->em
                            ->getRepository('\Entities\Service')
                            ->findOneByName($name);

                        /* directly associate the service with their account */
                        $person['account']->addService($service);

                        /* @TODO using the id as an index should prevent duplicate services
                         * from being saved, resulting in PDOException */
                        $username_services[] = $service; //$service->getId()

                        unset($service);
                    }
                }
            }

            /* Also add set of default iPlant Services & APIs */
            $default_services = $this->em
                ->getRepository('\Entities\Service')
                ->findByType('default');

            /* @TODO sometimes, a person may not be in ANY group in $service_to_group */

            /* Add default services requests so they can be managed */
            foreach ( $username_services as $owned ) {
                /* filter out current user services from the default services everyone should have */
                foreach ( $default_services as $i => $default ) {
                    if ( $owned->getName() === $default->getName() ) {
                        unset($default_services[$i]);

                        /* abort once found and prevent unneeded looping */
                        break;
                    }
                }
            }
            /* loop through the remaining default_services to request them from the daemon */
            foreach ( $default_services as $default_service ) {
                $request = new \Entities\ServiceRequest();
                $request->setService($default_service);
                $person['account']->addRequest($request);

                unset($default_service);
            }

            /* Flush again after adding the services */
            $this->em->flush();

            /* enable getting of the person object */
            $this->person = $person;

            /* clean up! */
            /* desperately hoping to avoid memory_limit problems */
            unset($research, $person, $emails, $email, $account, $profile, $institution, $groups, $service, $username_services, $default_services, $request, $ethnicity, $address, $phones, $phone);
        }

        /* concat $message before returning because of "interim password" message above */
        $message .= "Imported user: {$username} \r\n";
        return $message;
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
    public function getPerson()
    {
        return $this->person;
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

    /**
     * Class destructor
     *
     * @return void
     */
    public function __destruct() {
        /* Free up the connection... */
        ldap_unbind($this->ldap);

        /* Detaches all objects from Doctrine, invoking GC */
        $this->em->clear();

        /* advanced, but possibly unneccessary cleanup? */
        /*foreach ($this as $key => $value) {
            unset($this->$key);
        }*/

    }
}
