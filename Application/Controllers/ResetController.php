<?php

namespace Controllers;

use Doctrine\ORM\NoResultException;
use Silex\Application,
    Silex\ControllerCollection,
    Silex\ControllerProviderInterface;
use Symfony\Component\Form\FormError,
    Symfony\Component\Validator\Constraints;
use Iplant\Service\Notifier,
    Iplant\Service\UserImporter\LdapUserImporter,
    Iplant\Service\UserFinder\LdapUserFinder;

class ResetController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = new ControllerCollection();

        /**
         * Alias for /request
         */
        $controllers->get('/', function() use ($app) {
            return $app->redirect('/reset/request');
        });

        /**
         * Displays the form for password change requests
         *
         * This routes used to be called /registration/new to make it nice and CRUD like,
         * but, the url made better sense and was shorter as /registration
         */
        $controllers->get('/request', function() use ($app) {
            /* get and display the email form */
            $form = $app['form.factory']->createBuilder('form')
                ->add('username', 'text', array(
                    'required' => false,
                ))
                ->add('email', 'text', array(
                    'required' => false,
                ))
                ->add('recaptcha', 'recaptcha', array(
                    'invalid_message' => 'The Recaptcha did not match',
                    'widget_options' => array(
                        'theme' => 'white',
                        'use_ssl' => true,
                    ),
                ))->getForm();

            /* if logged in, prepopulate the username */
            if ( $auth = $app['session']->get('auth') ) {
                $form->setData(array('username' => $auth['name']));
            }

            return $app['twig']->render('reset/request.html.twig', array(
                'form' => $form->createView(),
                'action' => $app['url_generator']->generate('save_request', array(), true),
                'url_for_reminder' => $app['url_generator']->generate('remind_username', array(), true),
            ));

        })->bind('request_reset')
          ->requireHttps();

        /**
         * Saves a password reset token
         */
        $controllers->match('/request/save', function() use ($app) {
            /** Filter input! */
            $filter = $app['filter']('StripTags');
            $new_form_values = $filter->filter($app['request']->request->all());
            /* must inject filtered input back into $request so we can bind() later! */
            $app['request']->request->replace($new_form_values);

            /** Create the form */
            $form = $app['form.factory']->createBuilder('form')
                ->add('username', 'text', array(
                    'required' => false,
                ))
                ->add('email', 'text', array(
                    'required' => false,
                ))->add('recaptcha', 'recaptcha', array(
                    'invalid_message' => 'The Recaptcha did not match',
                    'widget_options' => array(
                        'theme' => 'white',
                        'use_ssl' => true,
                    ),
                ))->getForm();

            if ($app['request']->getMethod() === 'POST') {
                /* inject the form values into the corresponding entity */
                $form->bindRequest($app['request']);

                /** Main processing */
                if ( $form->isValid() ) {
                    try {
                        $data = $form->getData();

                        /* determine by which param to query */
                        switch (true) {
                            case (! empty($data['username']) ):
                                $account = $app['doctrine.orm.em']
                                    ->getRepository('\Entities\Account')
                                    ->findOneByUsername($data['username']);
                                break;

                            case (! empty($data['email'])):
                                /* Query for the user per email; we allow only unique email
                                 * addresses in the db, so we can safely do this here. */
                                $account = $app['doctrine.orm.em']
                                    ->getRepository('\Entities\Account')
                                    ->findOneByEmail($data['email']);
                                break;

                            /* both are empty! */
                            default:
                                throw new \Exception("You must enter either a username or email address.");
                        }

                        if ( false === $account ) {
                            /** Attempt to import from LDAP if user is notin Trellis */
                            $finder = new LdapUserFinder($app['config']->get('ldap', 'host'));

                            /* are they in LDAP? */
                            switch (true) {
                                case (! empty($data['username']) ):
                                    /* results may be an empty array or if any
                                     * results, an indexed array numerically
                                     * even if there was only 1 entry found */
                                    $entries = $finder->find(array('username' => $data['username']));
                                    if ( $entries['count'] === 0 ) {
                                        /* nope, not in LDAP either! */
                                        throw new \Exception(sprintf(
                                            "We could not find your account with username: '%s'", $data['username']
                                        ));
                                    }

                                    /* We use end() because we only want the most recent
                                     * entry if, God forbid there are multiples; otherwise
                                     * we still get the only entry */
                                    $entry = end($entries);
                                    $username = $data['username'];//$entry['uid'][0];
                                    $email = $entry['mail'][0];

                                    break;

                                case (! empty($data['email'])):
                                    /* results may be an empty array or if any
                                     * results, an indexed array numerically
                                     * even if there was only 1 entry found */
                                    $entries = $finder->find(array('email' => $data['email']));
                                    if ( $entries['count'] === 0 ) {
                                        /* nope, not in LDAP either! */
                                        throw new \Exception(sprintf(
                                            "We could not find your account with email address: '%s'", $data['email']
                                        ));
                                    }

                                    /* We use end() because we only want the most recent
                                     * entry if, God forbid there are multiples; otherwise
                                     * we still get the only entry */
                                    $entry = end($entries);
                                    $username = $entry['uid'][0];
                                    $email = $entry['mail'][0]; //$data['email']

                                    break;

                                default:
                                     break;
                            }

                            /* So, we've found the user in LDAP, but not in Trellis;
                             * Try to import from LDAP instead of failing! */
                            try {
                                $ldap_importer = new LdapUserImporter(
                                    $app['doctrine.orm.em'],
                                    $app['config']->get('ldap', 'host')
                                );

                                $ldap_importer->saveToTrellis($username);

                                /* We will use the account entity later */
                                $account = $ldap_importer->getPerson()->getAccount();

                            }
                            /* It seems we have a user in the db, either their
                             * email or username were already in trellis db, but
                             * not both. But, the other was found in LDAP... */
                            catch (\PDOException $pde) {
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
                                $pdo_error = $app['doctrine.orm.em']->getConnection()->errorInfo();
                                /** They provided an email address, whose username is already in Trellis! */
                                if ( ! empty($pdo_error[1]) ) {
                                    if ( in_array($pdo_error[1], array('1022','1062','1052','1169')) ) {
                                        /* we can recover! */
                                        //$reason = "Username exists already in Trellis";
                                        $account = $app['doctrine.orm.em']
                                            ->getRepository('\Entities\Account')
                                            ->findOneByUsername($username); // get username from ldap query at beginning of route
                                    }
                                }
                                /* They provided a username, whose associated email is already in Trellis! */
                                elseif ( ! empty($pde->errorInfo[1]) ) {
                                    /* We can recover! */
                                    $account = $app['doctrine.orm.em']
                                        ->getRepository('\Entities\Account')
                                        ->findOneByEmail($email); // get email from ldap query at beginning of route
                                    //$reason = "Email exists already in Trellis";
                                }

                                $app['monolog']->addError(sprintf(
                                    "PDO Exception: Resetting Password; Importing data from LDAP: %s, %s: %s", $reason, $pde->getMessage(), $pde->getTraceAsString()
                                ));

                                //@TODO, would continue here work??
                                //continue;

                            } catch (\Exception $e) {
                                $reason = "Not found in Trellis";
                                $app['monolog']->addError(sprintf(
                                    "PDO Exception: Resetting Password; Importing data from LDAP: %s, %s: %s", $reason, $e->getMessage(), $e->getTraceAsString()
                                ));

                                /* if we cannot import them, then we must fail because they are not in Trellis at all! */
                                throw new \Exception(
                                    'There was a problem with finding your account: ' .
                                    $e->getMessage()
                                );
                            }
                        }

                        /* disallow non-validated, existing accounts */
                        if ( ! $account->getIsValidated() ) {
                            return $app['twig']->render('reset/need_to_validate.html.twig', array(
                                'error' => 'There was an error when trying to save your password reset request.',
                            ));
                        }
                        $person = $account->getPerson();

                        /* create a token */
                        $token = new \Entities\Token('password');
                        $token->setAccount($account);

                        $app['doctrine.orm.em']->persist($token);
                        $app['doctrine.orm.em']->flush();

                        /* Email user of their url to change password */
                        $emails = $person->getEmails();
                        $notifier = new Notifier('mail', array(
                            'recipients' => array(
                                $emails[0]['email'],
                                'auth@iplantcollaborative.org'
                            ),
                            'subject' => '[iPlant] Password Reset Request',
                        ));
                        $reset_url = $app['url_generator']->generate('request_token', array('token' => $token->getToken()), true);
                        $notifier->notify(
                            /* html */
                            $app['twig']->render('emails/password_reset_email_to_user.html.twig', array(
                                'person' => $person,
                                'reset_url' => $reset_url,
                            )),
                            /* text */
                            $app['twig']->render('emails/password_reset_email_to_user.txt.twig', array(
                                'person' => $person,
                                'reset_url' => $reset_url,
                            ))
                        );

                        /* use post-redirect-get pattern to prevent multiple submissions
                         * if the user hits "Refresh" */
                        $app['session']->setFlash(
                            'success', "Your password reset link has been sent to {$emails[0]['email']}"
                        );

                        /* redirect differently if user is logged in */
                        if ( $auth = $app['session']->get('auth') ) {
                            return $app->redirect('/dashboard/');
                        }
                        else {
                            return $app->redirect('/');
                        }
                    }
                    catch (\Exception $e) {
                        /** We haz ewworZ! Gimme da form again so I canz see ewworz. */
                        $form->addError(new FormError($e->getMessage()));

                        return $app['twig']->render('reset/request.html.twig', array(
                            'form' => $form->createView(),
                            'action' => $app['url_generator']->generate('save_request', array(), true),
                            'url_for_reminder' => $app['url_generator']->generate('remind_username', array(), true),
                            'error' => 'Please check for errors below',
                        ));
                    }
                } else {
                    /** We haz ewworZ! Gimme da form again so I canz see ewworz. */
                    return $app['twig']->render('reset/request.html.twig', array(
                        'form' => $form->createView(),
                        'action' => $app['url_generator']->generate('save_request', array(), true),
                        'url_for_reminder' => $app['url_generator']->generate('remind_username', array(), true),
                        'error' => 'Please check for errors below',
                    ));
                }
            }

            /** backup for whatever else just might short-curcuit the above */
            return $app['twig']->render('reset/request.html.twig', array(
                'form' => $form->createView(),
                'action' => $app['url_generator']->generate('save_request', array(), true),
                'url_for_reminder' => $app['url_generator']->generate('remind_username', array(), true),
            ));

        })->method('GET|POST')
          ->bind('save_request')
          ->requireHttps();


        /**
         * Validate the password token sent to a user via email and present the
         * change password form.
         */
        $controllers->get('/password/{token}', function($token) use ($app) {
            /** Validate the md5 token */
            if ( ! preg_match('/^[0-9A-F]{32}$/', $token) ) {
                /* well, well; they tried to sneak a bad token by us, eh. */
                throw new \Exception("Your token was invalid");
            }

            /* if a record is not found, Doctrine2's findOne*() methods issues
             * an exception rather than false or 0, etc. */
            try {
                $user = $app['doctrine.orm.em']
                    ->getRepository('\Entities\Account')
                    ->findOneByPasswordToken($token);

            } catch (\Exception $e) {
                /* halt and exit with a thrown exception */
                throw new \Exception(
                    "We could not find your account."
                );
            }

            /* find the password token */
            try {
                $token_record = $app['doctrine.orm.em']
                    ->getRepository('\Entities\Token')
                    ->findOneByToken($token);

            } catch (\Exception $e) {
                 /* halt and exit with a thrown exception */
                throw new \Exception(
                    "We could not find your password reset token."
                );
            }

            /** Ensure its a password token */
            if ( 'password' !== $token_record->getPurpose() ) {
                /* halt and exit */
                throw new \Exception("Your token was invalid");
            }

            /** Check the token date for expiration */
            $expiration = $token_record->getExpiration();
            /* These are DateTime PHP5.3 objects */
            $now = new \DateTime('now');

            if ( $expiration < $now ) {
                /** Log it */
                $app['monolog']->addDebug(sprintf(
                    "User '%s' has attempted to change password with an expired token: '%s'.", $user->getUsername(), $token
                ));

                /** Redirect to help page */
                throw new \Exception(
                    "<p>Your request has expired. Please <a style=\"color:white;border-bottom:1px dotted white;\" href=\"/reset/request\">request another password reset</a>.</p>
                     <p class=\"small\">
                         You have either already used this reset request
                         before, or it's been longer than 14 days since you
                         requested to change your password.
                     </p>"
                );
            }

            $builder = $app['form.factory']
                ->createBuilder(new \Entities\Account\AccountForm(), $user)
                ->add('id', 'hidden')
                ->add('reset_token', 'hidden', array(
                    'data' => $token,
                    'property_path' => false,
                ))
                ->remove('username')
                ->add('recaptcha', 'recaptcha', array(
                    'invalid_message' => 'The Recaptcha did not match',
                    'widget_options' => array(
                        'theme' => 'white',
                        'use_ssl' => true,
                    ),
            ));
            $form = $builder->getForm();

            $emails = $user->getPerson()->getEmails();
            return $app['twig']->render('reset/password.html.twig', array(
                'form' => $form->createView(),
                'action' => $app['url_generator']->generate('update_password', array(), true),
                'success' => "Your request to change {$user->getUsername()}'s password was confirmed. It was sent to {$emails[0]['email']}",
            ));

        })->bind('request_token')
          ->requireHttps();

        /**
         * Update account with the new password
         */
        $controllers->match('/password/update', function() use ($app) {
            /** Filter input! */
            $filter = $app['filter']('StripTags');
            $new_form_values = $filter->filter($app['request']->request->all());
            /* must inject filtered input back into $request so we can bind() later! */
            $app['request']->request->replace($new_form_values);

            /** Query for the account */
            try {
                $account = $app['doctrine.orm.em']
                    ->getRepository('\Entities\Account')
                    ->findOneById($new_form_values['account']['id']);

            } catch (\Exception $e) {
                /* halt and exit */
                throw new \Exception('We could not find your account');
            }

            /* Needs to be near top since both form and query need it */
            $account_post = $app['request']->request->get('account');

            /** Create the form */
            $builder = $app['form.factory']->createBuilder(
                new \Entities\Account\AccountForm(), $account, array(
                    'validation_groups' => array('reset'),
                ))
                ->add('id', 'hidden')
                ->add('reset_token', 'hidden', array(
                    'data' => $account_post['reset_token'],
                    'property_path' => false,
                ))
                ->remove('username')
                ->add('recaptcha', 'recaptcha', array(
                    'invalid_message' => 'The Recaptcha did not match',
                    'widget_options' => array(
                        'theme' => 'white',
                        'use_ssl' => true,
                    ),
            ));
            $form = $builder->getForm();

            /** Main processing */
            if ($app['request']->getMethod() === 'POST') {
                /* Inject the form values into the corresponding entity */
                $form->bindRequest($app['request']);

                if ( $form->isValid() ) {

                    /* Mark it for the deamon */
                    $account->setStatus('update');

                    try {
                        $irods_request = $app['doctrine.orm.em']
                            ->getRepository('\Entities\ServiceRequest\IrodsRequest')
                            ->findOneByAccount($account->getId());

                        /* probably an imported user, which wouldn't have
                         * an irods request */
                        //@TODO Do we even need this here anymore since irods_requests are added in the importer?
                        if ( null === $irods_request ) {
                            /* try to create one */
                            $irods_service = $app['doctrine.orm.em']
                                ->getRepository('\Entities\Service')
                                ->findOneByName('iPlant Data Store');

                            $irods_request = new \Entities\ServiceRequest\IrodsRequest();

                            $irods_request->setApproval(true);
                            $irods_request->setStatus('update');
                            $irods_request->setAccount($account);
                            $irods_request->setService($irods_service);

                            /* Save it */
                            $app['doctrine.orm.em']->persist($irods_request);
                        }

                        $irods_request->setPassword($account->getPassword());

                    } catch (\Exception $e) {
                        /* if irods fails, halt the password change and log it! */
                        $app['monolog']->addError(sprintf(
                            "User '%s's attempt to change their password failed because: '%s'.", $account->getUsername(), $e->getMessage()
                        ));

                        throw new \Exception(
                            "There was an unexpected error, and your new password was not saved"
                        );
                    }
                    /** expire the token */
                    $token = $app['doctrine.orm.em']
                        ->getRepository('\Entities\Token')
                        ->findOneByToken($account_post['reset_token']);
                    /* Expire the token so it won't be used again */
                    $token->setExpiration(new \DateTime('now'));

                    try {
                        /* save updated token */
                        $app['doctrine.orm.em']->merge($token);
                        /* Save updated password */
                        $app['doctrine.orm.em']->merge($account);
                        /* actual DB write occurs in flush() */
                        $app['doctrine.orm.em']->flush();

                    } catch (\Exception $e) {
                        $app['monolog']->addDebug(
                           print_r($_POST, true) . "\r\n" .
                           print_r($app['request']->request->all(), true) . "\r\n" .
                           $app['doctrine.dbal.connection']->errorInfo() . "\r\n" .
                            $e->getTraceAsString()
                        );

                        throw new \Exception(
                            "There was an unexpected error, and your new password was not saved. iPlant support has been notified."
                        );
                    }

                    /** Set exit points */
                    if ( $auth = $app['session']->get('auth') ) {
                        /** Force logout */
                        /* prevents usage of further pages; it calls $storage->clear(), which
                         * is a Symfony class */
                        $app['auth']->clearIdentity();

                        /* regenerates the session id; also calls clear() */
                        $app['session']->invalidate();
                    }

                    /* redirect to homepage */
                    $app['session']->setFlash(
                        'success', '
                            <p>Your password has been successfully updated.</p>
                            <p>Please wait a minute before logging in so all your services can be updated.
                               <strong>If you are using icommands, please see this important <a href="https://pods.iplantcollaborative.org/wiki/display/start/Resetting+Your+Password">note about icommands and password resets</a></strong>.</p>'
                    );

                    return $app->redirect('/');

                } else {
                    /* Re-render with errors */
                    return $app['twig']->render('reset/password.html.twig', array(
                        'form' => $form->createView(),
                        'action' => $app['url_generator']->generate('update_password', array(), true),
                        'error' => "Please check the form for errors",
                    ));
                }
            }

            /* We don't process GET requests for this action;
             * just redisplay the form and hope they post it */
            return $app['twig']->render('reset/password.html.twig', array(
                'form' => $form->createView(),
                'action' => $app['url_generator']->generate('update_password', array(), true),
            ));

        })->method('GET|POST')
          ->bind('update_password')
          ->requireHttps();

        /**
         * Display the form to send a reminder email
         */
        $controllers->match('/username', function () use ($app) {
            /* get and display the email form */
            $form = $app['form.factory']->createBuilder('form')
                ->add('email', 'text', array(
                    'required' => true,
                ))
                ->add('recaptcha', 'recaptcha', array(
                    'invalid_message' => 'The Recaptcha did not match',
                    'widget_options' => array(
                        'theme' => 'white',
                        'use_ssl' => true,
                    ),
                ))
                ->getForm();

            return $app['twig']->render('reset/username.html.twig', array(
                'form' => $form->createView(),
                'action' => $app['url_generator']->generate('send_remind_username', array(), true),
            ));

        })->method('GET|POST')
          ->bind('remind_username')
          ->requireHttps();

        /**
         * Send the reminder email
         */
        $controllers->match('/username/send', function () use ($app) {
            /** Sanitize & Filter input! */
            $filter = $app['filter']('StripTags');
            $new_form_values = $filter->filter($app['request']->request->all());
            /* must inject filtered input back into $request so we can bind() later! */
            $app['request']->request->replace($new_form_values);

            /* Validate */
            $constraints = new Constraints\Collection(array(
                'fields' => array(
                    'email' => array(
                        new Constraints\NotBlank(array(
                            'message' => "Must fill in your registered email address",
                        )),
                        new Constraints\Email(array(
                            'message' => "Must be a valid email address",
                            'checkMX' => false,
                        )),
                    )),
                ));

            $form = $app['form.factory']->createBuilder('form', null, array(
                'validation_constraint' => $constraints,
                ))
                ->add('email', 'text', array(
                    'required' => true,
                ))
                ->add('recaptcha', 'recaptcha', array(
                    'invalid_message' => 'The Recaptcha did not match',
                    'widget_options' => array(
                        'theme' => 'white',
                        'use_ssl' => true,
                    ),
                ))
                ->getForm();

            /** Main processing */
            if ($app['request']->getMethod() === 'POST') {
                $form->bindRequest($app['request']);

                if ( $form->isValid() ) {
                    $data = $form->getData();

                    /* Query for the account by email to see if it exists */
                    try {
                        $person = $app['doctrine.orm.em']
                            ->getRepository('\Entities\Person')
                            ->findOneByEmail($data['email']);
                    }
                    catch (NoResultException $e) {
                        /* Re-render with errors */
                        return $app['twig']->render('reset/username.html.twig', array(
                            'form' => $form->createView(),
                            'action' => $app['url_generator']->generate('send_remind_username', array(), true),
                            'error' => "We could not find your account, using the email address: {$data['email']}",
                        ));
                    }

                    /* now, send the email */
                    $notifier = new Notifier('mail', array(
                        'recipients' => array(
                            $data['email'],
                            'auth@iplantcollaborative.org'
                        ),
                        'subject' => '[iPlant] Username Reminder',
                    ));
                    $notifier->notify(
                        /* html */
                        $app['twig']->render('emails/username_reminder_email_to_user.html.twig', array(
                            'person' => $person,
                        )),
                        /* text */
                        $app['twig']->render('emails/username_reminder_email_to_user.txt.twig', array(
                            'person' => $person,
                        ))
                    );

                    $app['session']->setFlash('success', "Your reminder email was sent. Please Check your email, including SPAM folders, to get your username.");
                    $app->redirect($app['url_generator']->generate('request_reset'));
                } else {
                    /* Re-render with errors */
                    return $app['twig']->render('reset/username.html.twig', array(
                        'form' => $form->createView(),
                        'action' => $app['url_generator']->generate('send_remind_username', array(), true),
                        'error' => "Please check the form for errors",
                    ));
                }
            }

            /* We don't process GET requests for this action;
             * just redisplay the form and hope they post it */
            return $app['twig']->render('reset/username.html.twig', array(
                'form' => $form->createView(),
                'action' => $app['url_generator']->generate('send_remind_username', array(), true),
            ));

        })->method('GET|POST')
          ->bind('send_remind_username')
          ->requireHttps();

        return $controllers;
    }
}
