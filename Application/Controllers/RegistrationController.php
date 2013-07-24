<?php

namespace Controllers;

use Silex\Application,
    Silex\ControllerCollection,
    Silex\ControllerProviderInterface;
use Doctrine\ORM\NoResultException;
use Iplant\Service\Notifier,
    Iplant\Service\UserFinder\LdapUserFinder,
    Iplant\Service\UserExporter\LotusUserExporter;
use Entities\Account\DuplicateException;
use Monolog\Logger,
    Monolog\Handler\StreamHandler;

class RegistrationController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = new ControllerCollection();

        /**
         * Displays the form for new registrants
         *
         * This routes used to be called /registration/new to make it nice and CRUD like,
         * but, the url made better sense and was shorter as /registration
         */
        $controllers->get('/', function() use ($app) {
            /* do not allow registration if already logged in */
            if ( $auth = $app['session']->get('auth') ) {
                throw new \LogicException('You are already registered.');
            }

            $person = new \Entities\Person();

            /* set survey question to yes by default */
            $profile = $person->getProfile();
            $profile->setParticipateInSurvey(1);

            $builder = $app['form.factory']->createBuilder(
                new \Entities\Person\PersonForm(), $person
            );
            /* Recaptcha must be here and not in PersonForm.php, otherwise
             * it interferes with Profile updating in the Dashboard */
            $builder->add('recaptcha', 'recaptcha', array(
                'widget_options' => array(
                    'theme' => 'white',
                    'use_ssl' => true,
                ),
            ));
            $form = $builder->getForm();

            return $app['twig']->render('registration/form.html.twig', array(
                'form' => $form->createView(),
                'action' => $app['url_generator']->generate('save_registration', array(), true),
            ));

        })->bind('show_registration')
          ->requireHttps();


        /**
         * Saves form data for a registration
         *
         * Only saves to a database. A back-end Python daemon will grab this data
         * and write to LDAP, create accounts in iRods, etc... so we don't have the
         * apache user getting its fingers into sensitive back-end processes.
         */
        $controllers->post('/save', function() use ($app) {
            /* do not allow registration if already logged in */
            if ( $auth = $app['session']->get('auth') ) {
                throw new \LogicException('You are already registered.');
            }

            $request = $app['request'];

            /** Filter input! */
            $filter = $app['filter']('StripTags');
            $new_form_values = $filter->filter($request->request->all());

            /* Accomodate institution as either an id (int) or a name (string) due to form autocomplete
             * Use ctype_digit() since input is a string and is_integer fails here,
             * but also check is_int for extra robustness and future-proofing
             * We do this early in processing so both success and error blocks
             * have access to this data so institution id does not display
             * after an error, instead of the institution name.
             */
            $institution = $new_form_values['person']['profile']['institution']['name'];
            if ( (ctype_digit($institution)) || (is_integer($institution)) ) {
                /* fetch the object for that institution id */
                $institution_object = $app['doctrine.orm.em']
                    ->getRepository('\Entities\Institution')
                    ->findOneById($new_form_values['person']['profile']['institution']['name']);

                /* $person is not yet available this early in the request
                $profile = $person->getProfile();
                $profile->setInstitution($institution_object);*/
                //$new_form_values['person']['profile']['institution']['id'] = $institution_object->getId();
                $new_form_values['person']['profile']['institution']['name'] = $institution_object->getName();
            }

            /* must inject filtered input back into $request so we can bind() later! */
            $request->request->replace($new_form_values);

            /* remove passwords! and other uneeded data for the log */
            unset(
                $new_form_values['person']['_token'],
                $new_form_values['person']['id'],
                $new_form_values['person']['account']['password'],
                $new_form_values['person']['account']['_token'],
                $new_form_values['person']['account']['id'],
                $new_form_values['recaptcha_challenge_field'],
                $new_form_values['recaptcha_response_field']
            );

            /** Create the form */
            $person = new \Entities\Person();

            $builder = $app['form.factory']->createBuilder(
                new \Entities\Person\PersonForm(), $person,
                array(
                    'validation_groups' => array('registration','default'),
                )
            );

            /* Recaptcha must be here and not in PersonForm.php, otherwise
             * it interferes with Profile updating in the Dashboard */
            $builder->add('recaptcha', 'recaptcha', array(
                'invalid_message' => 'The Recaptcha did not match',
                'widget_options' => array(
                    'theme' => 'white',
                    'use_ssl' => true,
                ),
            ));

            $form = $builder->getForm();

            if ($request->getMethod() == 'POST') {
                $form->bindRequest($request);

                if ( $form->isValid() ) {
                    /** Query LDAP for existing username */
                    $finder = new LdapUserFinder($app['config']->get('ldap', 'host'));

                    $username = $person->getAccount()->getUsername();
                    $entries = $finder->find(array('username' => $username));

                    if ( $entries['count'] > 0 ) {
                        throw new DuplicateException("
                            <p>Are you sure you did not already register?
                            <strong>'{$username}' is already in use for {$person->getFirstname()} {$person->getLastname()}.<strong></p>
                            <p>If this is you, please just <a href=\"/dashboard\">login</a>.</p>
                        ");
                    }

                    /** Add set of default iPlant Services & APIs */
                    $default_services = $app['doctrine.orm.em']
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
                        $person['account']->addRequest($request);
                        /* kill it to let the GC return some RAM */
                        $request = null;
                    }

                    /* Tokens have to be manually inserted. */
                    $person['account']->addToken(new \Entities\Token('validation'));

                    try {
                        /**Save, but throw if username already exists */
                        $app['doctrine.orm.em']->persist($person);

                        /* actual DB write occurs in flush() */
                        $app['doctrine.orm.em']->flush();

                        /** Log the successful registration data as a pseudo-backup */
                        $log = new Logger('Registration');
                        $log->pushHandler(new StreamHandler(__DIR__ . '/../../Logs/registrations.log', Logger::INFO));
                        $log->addInfo("New Registrant: " . json_encode($new_form_values));
                    }
                    catch (\PDOException $e) {
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

                        /** The user seems to be already registered */
                        /* log it, since we rarely get a PDOException, except in exceptional registrations. */
                        $app['monolog']->addError(sprintf(
                            "A new user has a PDO Error: %s, with stack trace: %s", $e->getMessage(), $e->getTraceAsString()
                        ));

                        $pdo_error = $app['doctrine.orm.em']->getConnection()->errorInfo();
                        if ( ! empty($pdo_error[1]) ) {
                        /** Handle duplicate usernames */
                            if ( in_array($pdo_error[1], array('1022','1062','1052','1169')) ) {
                                throw new DuplicateException("
                                    <p>Are you sure you did not already register?</p>
                                    <p>Your username '{$username}' is already in our system.</p>
                                    <p>If this is you, please just <a href=\"/dashboard\">login</a>.</p>
                                ");
                            }
                        } elseif ( ! empty($e->errorInfo[1]) ) {
                        /** Handle duplicate emails */
                            /* in PHP 5.4 we can do this: $code = PDO::errorInfo()[1]  yeah! */
                             if ( in_array($e->errorInfo[1], array('1022','1062','1052','1169')) ) {
                                 throw new DuplicateException("
                                    <p>Are you sure you did not already register?</p>
                                    <p>Your email address '{$person['emails'][0]['email']}' is already in our system.</p>
                                    <p>If this is you, please just <a href=\"/dashboard\">login</a>.</p>
                                ");
                             }
                        }

                        /** Its not a duplicate error, apparently? */
                        /* log failed registration data so we can manually re-create later, if needed */
                        $log = new Logger('FailedRegistration');
                        $log->pushHandler(new StreamHandler(__DIR__ . '/../../Logs/failed_registrations.log', Logger::INFO));
                        $log->addInfo("Failed Registrant: " . json_encode($new_form_values));

                        throw new \Exception("An unknown error occured; your registration was not saved, but the error was logged for iPlant Admin to review.");
                    }
                    catch (\Exception $e) {
                        /** Handle generic exception */
                        /* log failed registration data so we can manually re-create later, if needed */
                        $log = new Logger('FailedRegistration');
                        $log->pushHandler(new StreamHandler(__DIR__ . '/../../Logs/failed_registrations.log', Logger::INFO));
                        $log->addInfo("Failed Registrant: " . json_encode($new_form_values));

                        throw new \Exception("An unknown error occured; your registration was not saved, but the error was logged for iPlant Admin to review.");
                    }

                    /** Notify Systems group of the new registration */
                    $notifier = new Notifier('mail', array(
                        'recipients' => array('new-accounts@iplantcollaborative.org'),
                        'subject' => 'New User Registration',
                    ));
                    $notifier->notify(
                        /* html */
                        $app['twig']->render('emails/registration_email_to_admin.html.twig', array(
                            'person' => $person,
                        )),
                        /* text */
                        $app['twig']->render('emails/registration_email_to_admin.txt.twig', array(
                            'person' => $person,
                        ))
                    );

                    /* Export the new user to LOTUS */
                    $exporter = new LotusUserExporter('create');
                    try {
                        $exporter->export($person);

                    } catch (\Exception $e) {
                        /* log it */
                        $app['monolog']->addError(sprintf(
                            "Exporting to LOTUS failed for user: '%s': %s", $person->getAccount()->getUsername(), $e->getMessage()
                        ));
                    }

                    /* use post-redirect-get pattern to prevent multiple submissions
                     * if the user hits "Refresh" */
                    return $app->redirect('/register/completed');

                } else {
                    /** We haz ewworZ! Gimme da form again so I canz see ewworz. */
                    return $app['twig']->render('registration/form.html.twig', array(
                        'form' => $form->createView(),
                        'action' => $app['url_generator']->generate('save_registration', array(), true),
                        'error' => 'Please check for errors'
                    ));
                }
            }

            /** backup for a GET request */
            return $app['twig']->render('registration/form.html.twig', array(
                'form' => $form->createView(),
                'action' => $app['url_generator']->generate('save_registration', array(), true),
            ));

        })->bind('save_registration')
          ->requireHttps();


        /**
         * Display an instructions page after a successful registration
         */
        $controllers->get('/completed', function() use ($app) {
            return $app['twig']->render('registration/complete.html.twig', array());
        });

        /**
         * Validate the token sent to a user via email
         *
         * @TODO is Silex's new error handler useful here for bad input, etc?
         * just throw exceptions and let Silex handle and display it
         */
        $controllers->get('/validate/{token}', function($token) use ($app) {
            /** Validate the md5 token */
            if ( ! preg_match('/^[0-9A-F]{32}$/', $token) ) {
                /* well, well; they tried to sneak a bad token by us, eh. */
                throw new \Exception("Your token was invalid");
            }

            /* if a record is not found, Doctrine2 issues an exception rather
             * than false or 0, etc. */
            try {
                $user = $app['doctrine.orm.em']
                    ->getRepository('\Entities\Account')
                    ->findOneByToken($token);
            }
            catch (NoResultException $e) {
                /* Log it */
                $app['monolog']->addWarning(sprintf(
                    "An anonymous user has attempted to validate a token '%s' which does not exist.", $token
                ));

                throw new \Exception("
                    We could not find your token. Please contact Core Services.
                ");
            }

            /* Explicitly expire the token */
            /* should be just 1 token joined to this result, since the query filters by the validation token */
            //@TODO But, WHAT IF THERE IS MORE THAN 1 VALIDATION TOKEN!!
            $tokens = $user->getTokens();
            $tokens[0]->setExpiration(new \DateTime('now'));

            /* and...set them as validated */
            $user->setIsValidated(true);

            $app['doctrine.orm.em']->merge($user);
            /* actual DB write occurs in flush() */
            $app['doctrine.orm.em']->flush();

            /* flash message @TODO move somewhere else and use a flag instead? */
            $app['session']->setFlash('success', "You have successfully validated your account");

            /* Use post-redirect-get pattern to prevent multiple submissions if the user hits "Refresh".
             * We hard-coded the route, since url_generator doesn't work across controllers (?) */
            return $app->redirect('/register/validated');

        })->requireHttps();


        /**
         * Merely display a page to tell user they are validated and then
         * redirect them to /dashboard to login.
         */
        $controllers->get('/validated', function() use ($app) {
            return $app['twig']->render('registration/validated.html.twig', array());

        })->requireHttps();

        return $controllers;
    }
}
