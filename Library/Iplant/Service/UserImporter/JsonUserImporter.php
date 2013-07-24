<?php

namespace Iplant\Service\UserImporter;

use Doctrine\ORM\EntityManager;
use Iplant\Service\UserImporter\AbstractUserImporter;
use Iplant\Service\Exporter\LotusExporter;

class JsonUserImporter extends AbstractUserImporter
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
     * @param EntityManager $em
     *
     * @return void
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    /**
     * Saves a user in JSON to Trellis' database
     *
     * @param array $user
     *
     * @return string
     */
    public function saveToTrellis($user) {
        try {
            $message = "";

            $person = new \Entities\Person();
            $person->setFirstname($user['person']['firstname']);
            $person->setLastname($user['person']['lastname']);
            if ( isset($user['person']['gender']) ) {
                $person->setGender($user['person']['gender']);
            }
            if ( isset($user['person']['citizenship']) ) {
                $person->setGender($user['person']['citizenship']);
            }

            $ethnicity = $this->em
                ->getRepository('\Entities\Ethnicity')
                ->findOneById($user['person']['ethnicity']);
            if ( null !== $ethnicity ) {
               $person->setEthnicity($ethnicity);
            }

            $person->setNotes('Imported from Batch JSON file');
            $person->setStatus('add');

            /* Address */
            $address = new \Entities\Address();
            $address->setCity($user['person']['address']['city']);
            $address->setState($user['person']['address']['state']);
            $address->setCountry($user['person']['address']['country']);
            $person->setAddress($address);

            /* Email */
            /* this is because of Person's constructor which must create empty child objects */
            $emails = $person->getEmails();
            $email = $emails[0];
            $email->setEmail($user['person']['emails'][0]['email']);
            $email->setNotes('Imported from Batch JSON file');
            $email->setStatus('add');

            /* Phone */
            $phones = $person->getPhonenumbers();
            $phone = $phones[0];
            $phone->setNumber($user['person']['phonenumbers'][0]['number']);
            $phone->setNotes('Imported from Batch JSON file');
            $phone->setStatus('add');

            /* Account */
            $account = $person->getAccount();

            $interim_password = $account->createTempPassword();
            $account->setPassword($interim_password);
            $message .= "Set interim password {$interim_password} for user: {$user['person']['account']['username']} \r\n";

            /* we don't expect batch users to validate, do we? */
            $account->setUsername($user['person']['account']['username']);
            $account->setNotes('Imported from Batch JSON file');
            /* Tokens have to be manually inserted. */
            $token = new \Entities\Token('validation');
            $token->setIpAddress("");
            $person['account']->addToken($token);

            $account->setStatus('add');

            /* Profile */
            $profile = new \Entities\Profile();
            /* handle institution */
            $institution = $this->em
                ->getRepository('\Entities\Institution')
                ->findOneByName($user['person']['profile']['institution']['name']);

            if ( null !== $institution ) {
                $profile->setInstitution($institution);
            } else {
                $institution = new \Entities\Institution();
                $institution->setName($user['person']['profile']['institution']['name']);
                $institution->setStatus('completed');
                $profile->setInstitution($institution);
            }

            $profile->setDepartment($user['person']['profile']['department']);

            try {
                $position = $this->em
                    ->getRepository('\Entities\Position')
                    ->findOneByName($user['person']['profile']['position']);

                $profile->setPosition(position);
            }
            /* Position is not required, so just silently fail */
            catch (\Exception $e) {

            }

            $profile->setParticipateInSurvey($user['person']['profile']['participate_in_survey']);
            $profile->setHowHeardAbout($user['person']['profile']['how_heard_about']);
            //@TODO research_area must be looked up...
            $profile->setNotes('Imported from Batch JSON file');
            $profile->setStatus('add');
            $person->setProfile($profile);

            /** Services, default */
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
                    $request->setPassword($person->getAccount()->getPassword());

                } else {
                    $request = new \Entities\ServiceRequest();
                }

                $request->setService($service);
                $account->addRequest($request);

                /* kill it to let the GC return some RAM */
                $request = null;
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
                /* non-fatal; just note it... */
                $message .= sprintf("Exporting to LOTUS failed: %s", $e->getMessage());
            }

        } catch (\PDOException $pe) {
            /* in PHP 5.4 we can do this: $code = PDO::errorInfo()[1]  yeah! */
            $pdo_error = $this->em->getConnection()->errorInfo(); // or \PDOStatement->errorInfo()

            if ( in_array($pdo_error[1], array('1022','1062','1052','1169')) ) {
                $email = $person['emails'][0]['email'];

                throw new DuplicateException("
                    <p>Possible duplicate: username '{$username}' and/or email address '{$email}' are already in Trellis.</p>
                    <p>The full error is: {$pdo_error[2]}</p>
                ");

            } else {
                throw new \Exception(
                    "Database Error for user: {$person->getAccount()->getUsername()} :" . $pe->getMessage() . "\r\n" . $pdo_error[1] . ": " . $pdo_error[2]
                );
            }

        /* if its a non PDO Exception, just bubble it up */
        } catch (\Exception $e) {
            throw $e;
        }

        /* a string equates to true */
        $message .= "Imported user: {$user['person']['account']['username']}";
        
        return $message;
    }
}
