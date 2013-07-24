<?php

namespace Iplant\Service\UserImporter;

use Doctrine\ORM\EntityManager;
use Iplant\Service\UserImporter\AbstractUserImporter;
use Iplant\Service\Exporter\LotusExporter;

class LotusUserImporter extends AbstractUserImporter
{
    /**
     * The Doctrine Entity Manager
     *
     * @var EntityManager
     */
    protected $em;

    /**
     * Class constructor
     *
     * @param EntityManager $this->em
     *
     * @return void
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * (non-PHPdoc)
     * @see Iplant\Service\UserImporter.AbstractUserImporter::saveToTrellis()
     */
    public function saveToTrellis($username)
    {
        /** Import from Lotus */
        $handler = mysql_connect('localhost', '', '');
        if ( ! $handler ) {
            throw new \Exception(
                "Database connection to LOTUS failed before trying to import user '%s'.", $username
            );

            /* now, just stop */
            return false;
        }
        $database = mysql_select_db('', $handler);
        if ( ! $database ) {
            throw new \Exception(
                "Selecting the LOTUS database failed while trying to import user '%s'.", $username
            );

            /* now, just stop */
            return false;
        }

        $sql = "
            SELECT *
            FROM PARTICIPANTS p
            LEFT JOIN EMPLOYED_AT e ON e.PID = p.PID
            LEFT JOIN ORGANIZATION o ON o.OID = e.OID
            LEFT JOIN WORKS_IN w ON w.PID = p.PID
            LEFT JOIN RESEARCH r ON r.RID = w.RID
            WHERE p.iPlantUser = '{$username}'
        ";
        $result = mysql_query($sql, $handler);

        if ( ! $result ) {
            throw new \Exception(
                "Querying the LOTUS database for user '%s' failed while trying to import.", $username
            );

            /* now, just stop */
            return false;
        }

        while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
            $person = $this->em
                ->getRepository('\Entities\Person')
                ->findByUsername($username);

            if ( empty($person) ) {
                throw new \Exception(sprintf(
                    "Person was not found by username: %s", $username
                ));
            }

            /* Gender */
            switch ( $row['Gender'] ) {
                case 'M':
                    $gender = 'male';
                    break;
                case 'F':
                    $gender = 'female';
                    break;
                default:
                    $gender = null;
                    break;
            }
            $person->setGender($gender);

            /* Citizenship is nullable in the database schema */
            //@TODO mapping with current values....or manually update LOTUS so it has current values!
            $person->setCitizenship($row['Citizenship']);

            /* Ethnicity */
            $ethnicity_mapping = array(
                'Asian or Pacific Islander' => 'Asian',
                'White, including Arabic, not of Hispanic Origin' => 'Middle Eastern',
                'White, including Arabic, not of Hispanic Origin' => 'Caucasian',
                'White, Hispanic or Latino' => 'Hispanic',
                'African American or Black, not of Hispanic Origin' => 'African American',
                'American Indian, Alaskan Native, Hawaiian Native' => 'Native Hawaiian',
            );

            if ( $ethnicity_name = array_search($row['Ethnicity'], $ethnicity_mapping) ) {
                $ethnicity = $this->em
                    ->getRepository('\Entities\Ethnicity')
                    ->findOneByName($ethnicity_name);

                if ( null !== $ethnicity ) {
                    $person->setEthnicity($ethnicity);
                } //no else since we assume it was blank in the first place
            }

            /* City, State, Country */
            $address = new \Entities\Address();
            /* Schema will accept null for country, state, city */
            $address->setCountry($row['Country']);
            $address->setState($row['State']);
            $address->setCity($row['City']);
            $address->setStatus('completed');
            $person->setAddress($address);

            /* __construct() is not invoked in Entity context!
             * Therefore $person->getPhonenumbers() will return null! */
            $phone = new \Entities\Phonenumber();
            /* Schema will accept null for number */
            $phone->setNumber($row['Phone']);
            $phone->setStatus('completed');
            $person->addPhonenumber($phone);

            $profile = new \Entities\Profile();
            /* Department; null is OK */
            if ( (! isset($entry['departmentnumber'][0])) || (is_null($entry['departmentnumber'][0])) ) {
                $profile->setDepartment($row['Department']);
            }

            /** Extra Profile data */
            /* Institution; null is NOT OK for Profile::setInstitution() */
            if ( ! empty($row['Name']) ) {
                $institution = $this->em
                    ->getRepository('\Entities\Institution')
                    ->findOneByName($row['Name']);
                if ( null !== $institution ) {
                    $profile->setInstitution($institution);
                } else {
                    $institution = new \Entities\Institution();
                    $institution->setName($row['Name']);
                    $institution->setStatus('completed');
                    $profile->setInstitution($institution);
                }
            }

            /* Backup for Position: null is NOT OK */
            $position = $profile->getPosition();
            if ( (empty($position)) ) {
                /* set Position to generic 'Other' */
                $other_position = $this->em
                    ->getRepository('\Entities\Position')
                    ->findOneByName('Other');

                if ( null !== $other_position ) {
                    $profile->setPosition($other_position);
                }
            }

            $profile->setNotes($row['Summary']);

            /* Research Area; null is not OK for Profile::setResearchArea() */
            if ( ! empty($row['Area1']) ) {
                $research = $this->em
                    ->getRepository('\Entities\ResearchArea')
                    ->findOneByName($row['Area1']);

                if ( null !== $research ) {
                    $profile->setResearchArea($research);
                }
            }
        }

        /* save $person; we don't need persist() because this is an update */
        $this->em->flush();

        /* clean up! */
        mysql_free_result($result);

        /* ha! a string also returns true, so there! */
        return "Finished importing user {$person->getLastname()}, {$person->getFirstname()} ({$person->getAccount()->getUsername()})";
    }

    /**
     * Class destructor
     *
     * @return void
     */
    public function __destruct()
    {
        /* Free up the connection... */
        //mysql_close($handler);

        /* Detaches all objects from Doctrine, invoking GC */
        $this->em->clear();

        /* advanced, but possibly unneccessary cleanup? */
        /*foreach ($this as $key => $value) {
            unset($this->$key);
        }*/

    }

}
