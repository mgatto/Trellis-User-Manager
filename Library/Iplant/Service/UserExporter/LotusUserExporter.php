<?php

namespace Iplant\Service\UserExporter;

use Iplant\Service\UserExporter\AbstractUserExporter;
use Symfony\Component\Locale\Locale;

/**
 *
 *
 * Usage:
 * <code>
 * </code>
 *
 * PHP version 5
 *
 * @category  Trellis
 * @package   Data Exporter
 * @author    Michael Gatto <mgatto@iplantcollaborative.org>
 * @copyright 2012 iPlant Collaborative
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link
 * @filesource
 */
class LotusUserExporter extends AbstractUserExporter {

    /**
     * The database handler
     *
     * @var MySQL Resource
     */
    protected $connection;

    /**
     * Flag to signal update or new registration
     *
     * @var string
     */
    protected $eventType;

    /**
     * Class Constructor
     *
     * Sets the type of event the Exporter is responding to and connects to the
     * Lotus database.
     *
     * @return void
     */
    public function __construct($event)
    {
        $this->setEventType($event);
        $this->connection = $this->connect();
    }

    /**
     * Exports the data to Lotus
     *
     * @param Entities\Person $person
     *
     * @return bool
     */
    public function export(\Entities\Person $person)
    {
        switch ($this->getEventType()) {
            case 'update':
                try {
                    /* if the user is not in Lotus, do not add them for an update (?) == probably staff */
                    if ( ! $this->isTrackedByLotus($person) ) {
                        return false;
                    }

                    $username = $person->getAccount()->getUsername();
                    $participant_id = $this->saveParticipant($person);
                    $organization_id = $this->saveOrganization($person);
                    $this->updateEmployedAt($person, $participant_id, $organization_id);
                    $research_id = $this->saveResearch($person);
                    if ( ! empty($research_id) ) {
                        $this->updateWorksIn($participant_id, $research_id, $person->getAccount()->getUsername());
                    }
                } catch (\Exception $e) {
                    throw $e;
                }

                break;

            case 'create':
                try {
                    $username = $person->getAccount()->getUsername();
                    $participant_id = $this->saveParticipant($person);
                    $organization_id = $this->saveOrganization($person);

                    /* underlying table has no primary id nor is used in another table */
                    $this->insertEmployedAt($person, $participant_id, $organization_id);

                    $research_id = $this->saveResearch($person);
                    if ( ! empty($research_id) ) {
                        $this->insertWorksIn($participant_id, $research_id, $person->getAccount()->getUsername());
                    }
                } catch (\Exception $e) {
                    throw $e;
                }

                break;
            default:
                throw  new \Exception(sprintf(
                    "Export Failed: Event Type was not set. Cannot continue for user: '%s'", $person->getAccount()->getUsername()
                ));

        }

        return true;
    }

    /**
     * Connects to the Lotus Database for further operations
     *
     * @return MySQL Connection
     */
    protected function connect()
    {
        $handler = mysql_connect('localhost', '', '');
        if ( ! $handler ) {
            throw new \Exception(sprintf(
                "Database connection to LOTUS failed before trying to export a user: %s", mysql_errno($handler)
            ));
        }

        $database = mysql_select_db('lotus', $handler);
        if ( ! $database ) {
            throw new \Exception(sprintf(
                "Selecting the LOTUS database failed while trying to export a user: %s", mysql_errno($handler)
            ));
        }

        return $handler;
    }

    /**
     * Inserts a new person, or update a PARTICIPANT row
     *
     * @param \Entities\Person $person
     *
     * @return bool
     *
     * @throws Exception
     */
    protected function saveParticipant(\Entities\Person $person)
    {
        $username = $person->getAccount()->getUsername();

        /* gender */
        $gender = $this->mapGender($person->getGender());

        /* ethnicity */
        $ethnicity_entity = $person->getEthnicity();
        if ( ! empty($ethnicity_entity) ) {
            $ethnicity = $this->mapEthnicity($ethnicity_entity->getName());
        } else {
            $ethnicity = null;
        }

        $citizenship = $person->getCitizenship();
        if ( ! empty($citizenship) ) {
            $is_us_citizen = ( 'US' === $citizenship ) ? 'Y' :'N' ;

            /* \Locale refers to the PHP built-in class in the intl extension;
             * Locale refers to \Symfony\Component\Locale\Locale */
            $citizenships = array('ZZ' => 'Decline to Answer') + Locale::getDisplayCountries(\Locale::getDefault());
            $display_citizenship = $citizenships[$citizenship];

        } else {
            $is_us_citizen = '';
            $display_citizenship = '';
        }

         /* Email */
        $emails = $person->getEmails();
        $email = $emails[0]['email'];

        /* Phone */
        $phones = $person->getPhonenumbers();
        $phone = $phones[0]['number'];

        /* Do we need to insert or update */
        $sql_has_participant = "
            SELECT * FROM PARTICIPANTS
            WHERE iPlantUser = '{$username}'
        ";
        $participant_result = mysql_query($sql_has_participant, $this->connection);
        $has_participant = mysql_fetch_assoc($participant_result);

        /* it has to test for false, since mysql_query returns a resource or false */
        if ( false !== $has_participant ) {
            /* update! */
            $sql_update = "
                UPDATE PARTICIPANTS p
                SET
                FName = '" . $person->getFirstname() . "',
                LName = '" . $person->getLastname() . "',
                Gender = '" . $gender . "',
                Citizenship = '" . $display_citizenship . "',
                USCitizen = '" . $is_us_citizen . "',
                Ethnicity = '" . $ethnicity . "',
                Email = '" . $email . "',
                Phone = '" . $phone . "',
                City = '" . $person->getAddress()->getCity() . "',
                State = '" . $person->getAddress()->getState() . "',
                Country = '" . $person->getAddress()->getCountry() . "'
                WHERE p.iPlantUser = '$username'
            ";

            $update_result = mysql_query($sql_update, $this->connection);
            if ( false === $update_result ) {
                throw new \Exception("Updating table: 'PARTICIPANT' failed for user: '{$username}'");
            }

        } else {
            /* insert new! */
            $sql_insert = "
                INSERT INTO PARTICIPANTS
                (FName, LName, Gender, Citizenship, USCitizen, Ethnicity, iPlantUser, Email,
                 Phone, City, State, Country)
                VALUES (
                    '{$person->getFirstname()}',
                    '{$person->getLastname()}',
                    '{$gender}',
                    '{$display_citizenship}',
                    '{$is_us_citizen}',
                    '{$ethnicity}',
                    '{$username}',
                    '{$email}',
                    '{$phone}',
                    '{$person->getAddress()->getCity()}',
                    '{$person->getAddress()->getState()}',
                    '{$person->getAddress()->getCountry()}'
                )
            ";

            $insert_result = mysql_query($sql_insert, $this->connection);
            if ( false === $insert_result ) {
                /* Exceptions are caught in self::export() */
                throw new \Exception("Inserting new 'PARTICIPANT' failed: " . mysql_error($this->connection) . $sql_insert);
            }

        }

        /* must re-query just to get the id! */
        $sql = "
            SELECT p.PID
            FROM PARTICIPANTS p
            WHERE p.iPlantUser = '$username'
        ";
        $result = mysql_query($sql, $this->connection);
        $particpant = mysql_fetch_assoc($result);

        return $particpant['PID'];
    }

    /**
     * Saves and organization to Lotus
     *
     * Will check for an existing organization with a name similar to the one
     * which was passed in. If it exists, it returns that one. Otherwise, it
     * inserts it and returns the id of the newly inserted organization.
     *
     * @param \Entities\Person $person
     *
     * @return int
     */
    protected function saveOrganization(\Entities\Person $person)
    {
        /* Is this organization already in LOTUS? */
        $sql_has_organization = "
            SELECT * FROM ORGANIZATION
            WHERE Name LIKE '%{$person->getProfile()->getInstitution()->getName()}%'
        ";

        $organization_result = mysql_query($sql_has_organization, $this->connection);
        $organization = mysql_fetch_assoc($organization_result);

        /* tests for false since mysql_query returns a resource or false */
        if ( false === $organization ) {

            /* Org not found; insert it */
            $sql_new_organization = "
                INSERT INTO ORGANIZATION (Name)
                VALUES ('{$person->getProfile()->getInstitution()->getName()}')
            ";
            $new_organization_result = mysql_query($sql_new_organization, $this->connection);

            /* must re-query just to get the id! */
            $sql = "
                SELECT OID
                FROM ORGANIZATION
                WHERE Name = '{$person->getProfile()->getInstitution()->getName()}'
            ";
            $oid_result = mysql_query($sql, $this->connection);
            if ( false === $oid_result ) {
                /* Exceptions are caught in self::export() */
                throw new \Exception("Inserting new Organization into LOTUS failed: " . mysql_error($this->connection) . $sql);
            }

            $new_organization = mysql_fetch_assoc($oid_result);

            return $new_organization['OID'];

        } else {

            /* it exists! */
            return $organization['OID'];
        }
    }

    /**
     * Inserts a row in Lotus' EMPLOYED_AT table
     *
     * @param \Entities\Person $person
     * @param int $participant_id
     * @param int $organization_id
     *
     * @return bool
     */
    protected function insertEmployedAt($person, $participant_id, $organization_id)
    {
        /* Position may return null, especially since new switchover to fixed position choices */
        $position = $person->getProfile()->getPosition();
        $role = ( empty($position) ) ? '' : $position->getName();

        $sql_new_eployed_at = "
            INSERT INTO EMPLOYED_AT
            (PID,OID,Department,Role)
            VALUES (
                {$participant_id},
                {$organization_id},
                '{$person->getProfile()->getDepartment()}',
                '{$role}'
            )
        ";
        $result = mysql_query($sql_new_eployed_at, $this->connection);

        if ( false === $result ) {
            throw new \Exception("Employed At failed to insert: " . mysql_error($this->connection));
        }

        return true;
    }

    /**
     * Updates a person's row in Lotus' EMPLOYED_AT table
     *
     * @param \Entities\Person $person
     * @param int $participant_id
     * @param int $organization_id
     *
     * @return bool
     */
    protected function updateEmployedAt($person, $participant_id, $organization_id)
    {
        /* Position may return null, especially since new switchover to fixed position choices */
        $position = $person->getProfile()->getPosition();
        $role = ( empty($position) ) ? '' : $position->getName();

        $sql_update_employed_at = "
            UPDATE EMPLOYED_AT
            SET
                OID = {$organization_id},
                Department = '{$person->getProfile()->getDepartment()}',
                Role = '{$position->getName()}'
            WHERE PID = {$participant_id}
        ";
        $result = mysql_query($sql_update_employed_at, $this->connection);

        if ( false === $result ) {
            throw new \Exception("Employed At failed to update for '{$person->getAccount()->getUsername()}':" . mysql_error($this->connection));
        }

        return true;
    }

    /**
     * Adds or updates a row in Lotus' RESEARCH table
     *
     * @param \Ethnicities\Person $person
     *
     * @return int
     */
    protected function saveResearch($person)
    {
        /* not a typo; this evaluates to true if the person has a research area (?) */
        if ( $research_entity = $person->getProfile()->getResearchArea() ) {
            $research_area = $research_entity->getName();
        } else {
            return false;
        }

        /* no point in continuing if they never filled in the optional research area */
        if ( empty($research_area) ) {
            return null;
        }

        /* is the Area2 already in LOTUS? */
        $sql_has_research = "
            SELECT * FROM RESEARCH
            WHERE Area2 = '{$research_area}'
        ";
        $research_result = mysql_query($sql_has_research, $this->connection);
        $research = mysql_fetch_assoc($research_result);

        /* it has to test for false, since mysql_query returns a resource or false */
        if ( false === $research ) {
            /* Research not found; insert it */
            $sql_new_research = "
                INSERT INTO RESEARCH (Area2)
                VALUES ('{$research_area}')
            ";
            $result = mysql_query($sql_new_research, $this->connection);

            /* must re-query just to get the id! */
            $sql_get_rid = "
                SELECT RID
                FROM RESEARCH
                WHERE Area2 = '{$research_area}'
            ";
            $rid_result = mysql_query($sql_get_rid, $this->connection);
            $new_research = mysql_fetch_assoc($rid_result);

            return $new_research['RID'];

        } else {
            /* it exists! */
            return $research['RID'];
        }
    }

    /**
     * Add a row to Lotus' WORKS_IN table
     *
     * @param int $participant_id
     * @param int $research_id
     *
     * @return bool
     */
    protected function insertWorksIn($participant_id, $research_id, $username)
    {
        $sql_new_works = "
            INSERT INTO WORKS_IN (PID, RID)
            VALUES (
                {$participant_id},
                {$research_id}
            )
        ";
        $result = mysql_query($sql_new_works, $this->connection);

        if ( false === $result ) {
            throw new \Exception("Inserting table 'WORKS_AT' failed for user '{$username}': " . mysql_error());
        }

        return true;
    }

    /**
     * Updates Lotus' WORKS_IN table
     *
     * @param int $participant_id
     * @param int $research_id
     *
     * @return bool
     */
    protected function updateWorksIn($participant_id, $research_id, $username)
    {
        $sql_update_works_in = "
            UPDATE WORKS_IN
            SET RID = {$research_id}
            WHERE PID = {$participant_id}
        ";
        $result = mysql_query($sql_update_works_in, $this->connection);

        if ( false === $result ) {
            throw new \Exception("Updating table 'WORKS_IN' failed for user '{$username}': " . mysql_error());
        }

        return true;
    }

    /**
     * Sets the flag for which event we are interacting with Lotus
     *
     * @param string $event
     *
     * @return LotusExporter
     */
    protected function setEventType($event)
    {
        if ( ! in_array($event, array('create','update')) ) {
            return new \Exception("'{$event}' is not a valid event type");
        }

        $this->eventType = $event;

        return $this;
    }

    /**
     * Gets the type of event we are executing: update or create
     *
     * @return string
     */
    protected function getEventType()
    {
        return $this->eventType;
    }

    /**
     * Is the user already in LOTUS?
     *
     * @param Entities\Person $person
     *
     * @return bool
     */
    protected function isTrackedByLotus($person)
    {
        $is_tracked = null;

        /* Query for person */
        $username = $person->getAccount()->getUsername();
        $sql = "
            SELECT *
            FROM PARTICIPANTS p
            WHERE p.iPlantUser = '$username'
        ";
        $result = mysql_query($sql, $this->connection);

        if ( $result ) {
            $is_tracked = true;
        } else {
            $is_tracked = false;
        }

        /* clean up! */
        mysql_free_result($result);

        return $is_tracked;
    }

    /**
     * map the gender type between Lotus' format and Trellis'
     *
     * @param string $gender
     *
     * @return string
     */
    protected function mapGender($gender)
    {
        /* let's just not produce PHP Warnings...and terminate here */
        if ( empty($gender) ) {
            return null;
        }

        switch ( $gender ) {
            case 'male':
                return 'M';
                break;
            case 'female':
                return 'F';
                break;
            default:
                return null;
                break;
        }
    }

    /**
     * map the ethnicity type between Lotus' format and Trellis'
     *
     * @param string $ethnicity
     *
     * @return string
     */
    protected function mapEthnicity($ethnicity)
    {
        /* let's just not produce PHP Warnings...and terminate here */
        if ( empty($ethnicity) ) {
            return null;
        }

        $ethnicity_mapping = array(
            'Asian or Pacific Islander' => 'Asian',
            'White, including Arabic, not of Hispanic Origin' => 'Middle Eastern',
            'White, including Arabic, not of Hispanic Origin' => 'Caucasian',
            'White, Hispanic or Latino' => 'Hispanic',
            'African American or Black, not of Hispanic Origin' => 'African American',
            'American Indian, Alaskan Native, Hawaiian Native' => 'Native Hawaiian',
        );

        if ( array_key_exists($ethnicity, $ethnicity_mapping) ) {
            return $ethnicity_mapping[$ethnicity];
        } else {
            return null;
        }
    }

}
