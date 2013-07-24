<?php

namespace Iplant\Service\UserImporter;

use Doctrine\ORM\EntityManager;
use Iplant\Service\UserImporter\AbstractUserImporter;
use Iplant\Service\Exporter\LotusExporter;

class CsvUserImporter extends AbstractUserImporter
{
    /**
     * The Doctrine Entity Manager
     *
     * @var EntityManager
     */
    protected $em;

    /**
     * CSV Headers for thr rows
     *
     * @var array|mixed
     */
    protected $headers;

    /**
     * Class constructor
     *
     * @param EntityManager $em
     *
     * @return void
     */
    public function __construct(EntityManager $em, array $headers)
    {
        $this->em = $em;

        /*if ( ! $this->hasHeaders($headers) ) {
            throw new \RuntimeException("Headers must be present in CSV file!");
        }*/
        $this->headers = $headers;
    }

    /**
     * Save the parsed CSV data to Trellis' database
     *
     * @param array $user
     *
     * @return string
     */
    public function saveToTrellis($user) {
        $message = "";

        try {
            $person = new \Entities\Person();
            $person->setFirstname($user['first_name']);
            $person->setLastname($user['last_name']);
            $person->setNotes('Imported from Batch CSV file');
            $person->setStatus('pending');

            $address = new \Entities\Address();
            $address->setCity($user['city']);
            $address->setState($user['state']);
            $address->setCountry($user['country']);

            $person->setAddress($address);

            /* this is because of Person's constructor which must create empty child objects */
            $emails = $person->getEmails();
            $email = $emails[0];
            $email->setEmail($user['email']);
            $email->setNotes('Imported from Batch CSV file');
            $email->setStatus('add');

            $account = $person->getAccount();

            $interim_password = $account->createTempPassword();
            $account->setPassword($interim_password, false); //true?

            $message .= "Set interim password {$interim_password} for user: {$user['username']} \r\n";

            /* we don't expect batch users to validate, do we? */
            $account->setIsValidated(true);
            $account->setUsername($user['username']);
            $account->setNotes('Imported from Batch CSV file');
            $account->setStatus('add');

            $profile = new \Entities\Profile();

            /* handle institution */
            $institution = $this->em
                ->getRepository('\Entities\Institution')
                ->findOneByName($user['institution']);

            if ( null !== $institution ) {
                $profile->setInstitution($institution);
            } else {
                $institution = new \Entities\Institution();
                $institution->setName($user['institution']);
                $institution->setStatus('completed');
                $profile->setInstitution($institution);
            }

            $profile->setDepartment($user['department']);

            try {
                $position = $this->em
                    ->getRepository('\Entities\Position')
                    ->findOneByName($user['position']);

                $profile->setPosition($position);
            }
            /* Position is not required, so just silently fail */
            catch (\Exception $e) {

            }

            $profile->setNotes('Imported from Batch CSV file');
            $profile->setStatus('add');
            $person->setProfile($profile);

            /** Add set of default iPlant Services & APIs */
            $default_services = $this->em
                ->getRepository('\Entities\Service')
                ->findByType('default');

            /* create a new request for each default service */
            foreach ($default_services as $service) {
                /* These default services do not require approval.
                 * approval = pending is set by default in the ServiceRequest model */
                if ( 'iPlant Data Store' === $service->getName() ) {
                    /* irods needs the raw password before we hash it upon preSave() */
                    $request = new \Entities\ServiceRequest\IrodsRequest();
                    $request->setPassword($person['account']->getPassword());

                } else {
                    $request = new \Entities\ServiceRequest();
                }

                $request->setService($service);
                $account->addRequest($request);

                /* kill it to let the GC return some RAM */
                $request = null;
            }

            /* collect the headers */
            foreach ($this->headers as $header) {
                if ( (stristr($header, 'dnasubway')) || (stristr($header, 'atmosphere')) ) {
                    $field = explode('@', $header);
                    $user['services'][$field[0]][$field[1]] = $user[$header];
                }
            }

            /* add the extra services */
            foreach ( $user['services'] as $key => $service_to_add ) {
                /* process service requests seperately */
                switch ( $key ) {
                    case 'dnasubway':
                        /* actual id may vary across testing & production servers... */
                        $dnasubway_request = new \Entities\ServiceRequest\DnaSubwayRequest();

                        /** required attributes */
                        $service = $this->em
                            ->getRepository('\Entities\Service')
                            ->findOneByName('DNA Subway');

                        $dnasubway_request->setService($service);
                        $dnasubway_request->setHowWillUse($service_to_add['how_will_use']);

                        /** Optional attributes */
                        if ( (isset($service_to_add['school_name'])) && (! empty($service_to_add['school_name'])) ) {
                            $dnasubway_request->setSchoolName($service_to_add['school_name']);
                        }

                        if ( (isset($service_to_add['school_type'])) && (! empty($service_to_add['school_type'])) ) {
                            $dnasubway_request->setSchoolType($service_to_add['school_type']);
                        }

                        if ( (isset($service_to_add['school_surrounding_area'])) && (! empty($service_to_add['school_surrounding_area'])) ) {
                            $dnasubway_request->setSchoolSurroundingArea($service_to_add['school_surrounding_area']);
                        }

                        /* must-have relation to the account */
                        $account->addRequest($dnasubway_request);
                        break;

                    case 'atmosphere':
                        $atmosphere_request = new \Entities\ServiceRequest\AtmosphereRequest();

                        /* required attributes */
                        $service = $this->em
                            ->getRepository('\Entities\Service')
                            ->findOneByName('Atmosphere');

                        $atmosphere_request->setService($service);
                        $atmosphere_request->setApproval('approved');
                        $atmosphere_request->setHowWillUse($service_to_add['how_will_use']);

                        /* must-have relation to the account */
                        $account->addRequest($atmosphere_request);
                        break;

                    default:
                        ;
                        break;
                }
            }

            /* save now! */
            $this->em->persist($person);
            /* actual DB write occurs in flush() */
            $this->em->flush();

            /* Export the new user to LOTUS */
            $exporter = new LotusExporter('create');
            try {
                $message .= $exporter->export($person);

            } catch (\Exception $e) {
                $message .= sprintf("Exporting to LOTUS failed: %s", $e->getMessage());
            }

        } catch (\PDOException $pe) {
            /* in PHP 5.4 we can do this: $code = PDO::errorInfo()[1]  yeah! */
            /*
             * 23000 is actually the SQL state shared by many different errors.
             * The unique codes are below:
             *
             * 1022 SQLSTATE: 23000 (ER_DUP_KEY) "Can't write; duplicate key in table"
             * 1048 SQLSTATE: 23000 (ER_BAD_NULL_ERROR)
             * 1052 SQLSTATE: 23000 (ER_NON_UNIQ_ERROR)
             * 1062 SQLSTATE: 23000 (ER_DUP_ENTRY) [for key]
             * 1169 SQLSTATE: 23000 (ER_DUP_UNIQUE) [uniqie constraint]
             * 1216 SQLSTATE: 23000 (ER_NO_REFERENCED_ROW) "Cannot add or update a child row: a foreign key constraint fails"
             * 1217 SQLSTATE: 23000 (ER_ROW_IS_REFERENCED) "Cannot delete or update a parent row: a foreign key constraint fails"
             * 1451 SQLSTATE: 23000 (ER_ROW_IS_REFERENCED_2) "Cannot delete or update a parent row: a foreign key constraint fails"
             * 1452 SQLSTATE: 23000 (ER_NO_REFERENCED_ROW_2) "Cannot add or update a child row: a foreign key constraint fails"
             *
             * We can get the true code in: (array) PDOException::errorInfo()
             */

            /* The user seems to already registered */
            $pdo_error = $this->em->getConnection()->errorInfo();
            if ( in_array($pdo_error[1], array('1022','1062','1052','1169')) ) {
                $email = $person['emails'][0]['email'];
                /* overwrite whatever messages we've already written */
                $message .= "Error for user: {$person->getAccount()->getUsername()} :" . $pe->getMessage() . "\r\n" . $pdo_error[1] . "\r\n" . $pdo_error[2];
            } else {
                /* overwrite whatever messages we've already written */
                $message .= "Error for user: {$person->getAccount()->getUsername()} :" . $pe->getMessage() . "\r\n" . $pdo_error[1];
            }

        } catch (\Exception $e) {
            $message .= "Error for user: {$person->getAccount()->getUsername()} :" . $e->getMessage() . "\r\n";
        }

        $message .= "Imported user: {$user['username']} \r\n";
        return $message;
    }

    /**
     * Ensure required headers are present in the CSV file.
     *
     * Extra headings will be ignored.
     *
     * @param array $headers
     *
     * @return boolean
     */
    private function hasHeaders($headers) {

        $required_headers = array(
            "last_name",
            "first_name",
            "city",
            "state_province",
            "country",
            "email",
            "institution",
            "department",
            "position",
            "username",
        );

        $difference = array_diff($headers, $required_headers);

        /* only throw if any required headers are missing */
        if ( ! empty($difference) ) {
            return false;
        }

        return true;
    }
}
