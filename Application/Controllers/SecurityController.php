<?php

namespace Controllers;

use Silex\Application,
    Silex\ControllerCollection,
    Silex\ControllerProviderInterface;

use Iplant\Service\UserImporter\LotusUserImporter,
    Iplant\Service\UserImporter\LdapUserImporter,
    Iplant\Service\UserFinder\LdapUserFinder;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use Entities\Account\DuplicateException;

class SecurityController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = new ControllerCollection();

        /** ROUTES */
        /** Ensure users coming from CAS are valid and are allowed to use this application */
        $controllers->match('/authenticate', function() use ($app) {
            /* Filter input, even though its coming from CAS */
            $filter = $app['filter']('StripTags');
            $filtered_values = $filter->filter($app['request']->request->all());
            /* must inject filtered input back into $request so we can bind() later! */
            $app['request']->request->replace($filtered_values);

            /* Prevent endless loops, since otherwise hasTicket() will always
             * return false */
            $app['auth.adapter']->setQueryParams($app['request']->query->all());
            $app['auth.adapter']->setTicket();

            if ( $app['auth.adapter']->hasTicket() ) {
                $result = $app['auth']->authenticate($app['auth.adapter']);

                /* We won't check isValid(), but instead switch according to the raw
                 * result code; yes, we do need to do this... */
                switch ($result->getCode()) {

                    /* user not found in Users table */
                    case -1: // == Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND
                        /* attempt to find in LDAP instead */
                        $finder = new LdapUserFinder($app['config']->get('ldap', 'host'));

                        $entries = $finder->find(array('username' => $result->getIdentity()));
                        if ( $entries['count'] > 0 ) {
                            /* they are already in LDAP, but not Trellis: import! */
                            try {
                                $ldap_importer = new LdapUserImporter(
                                    $app['doctrine.orm.em'],
                                    $app['config']->get('ldap', 'host')
                                );
                                //@TODO would be nice to just pass the $entry we already got, eh!
                                $ldap_importer->saveToTrellis($entries[0]['uid'][0]);

                            } catch (\Exception $e) {
                                /* this is critical; fail here for sure */
                                /* log the details for Admins; show generic message upon rethrow */
                                $app['monolog']->addError(sprintf(
                                    "Importing from LDAP upon login failed: '%s'\r\n %s.", $e->getMessage(), $e->getTraceAsString()
                                ));
                                //throw $e;
                                throw new \Exception("There was a critical error upon logging in."); //Please contact Iplant Support.
                            }

                            /** Import from Lotus */
                            try {
                                $lotus_importer = new LotusUserImporter($app['doctrine.orm.em']);
                                $lotus_importer->saveToTrellis($entries[0]['uid'][0]);

                            } catch (\Exception $e) {
                                /* this is not critical; just log it and proceed! */
                                $app['monolog']->addError(sprintf(
                                    "Importing from LOTUS upon login failed: '%s'.", $e->getMessage()
                                ));
                            }

                            /* hard-coded route, since url_generator doesn't work across controllers (?) */
                            return $app->redirect('/dashboard/');

                        } else {
                            /* if not found in LDAP, redirect to Registration */
                            $app['session']->setFlash(
                                'error', 'We could not find your account. Please register.'
                            );

                            /* hard-coded route, since url_generator doesn't work across controllers (?) */
                            return $app->redirect('/register/');
                        }

                        $ldap->disconnect();

                        break;

                    /* successfully authenticated by CAS and found in user db */
                    case 1: // == Zend_Auth_Result::SUCCESS
                        $user = $result->getIdentity();
                        /* in PHP 5.4, we can just do this: $result->getIdentity()['name'] ! */
                        $username = $user['name'];

                        /** Ensure account in LDAP is in sync with Trellis */
                        if ( ! $app['debug'] ) {
                            /* Get full entry from LDAP only when in production... */
                            $finder = new LdapUserFinder($app['config']->get('ldap', 'host'));
                            $entries = $finder->find(array('username' => $username));

                            /* get person from DB */
                            $person = $app['doctrine.orm.em']
                                ->getRepository('\Entities\Person')
                                ->findByUsername($username);

                            /* Get email address (first/last name, too?) */
                            $emails = $person->getEmails();
                            $email = $emails[0];
                            $email_in_trellis = $email->getEmail();
                            $email_in_ldap = $entries[0]['mail'][0];

                            /* Mismatches? LDAP takes precedence!
                             * Email can only be changed in LDAP? */
                            if ( 0 !== strcmp($email_in_ldap, $email_in_trellis) ) {
                                /* update entity and save */
                                $email->setEmail($email_in_ldap);
                                $app['doctrine.orm.em']->merge($email); //merge()?
                                $app['doctrine.orm.em']->flush();
                            }
                        }

                        /* redirect to dashboard */
                        return $app->redirect('/dashboard/');
                        break;

                    /* User has not yet validated */
                    case -4: // == Zend_Auth_Result::FAILURE_UNCATEGORIZED
                        return $app['twig']->render('security/must_validate.html.twig', array());
                        break;

                    default:
                        $message = join(" ", $result->getMessages());

                        throw new AccessDeniedHttpException($message);
                        break;
                }
            }

        })->method('POST|GET')
          ->bind('do_login')
          ->requireHttps();;

        /** Logout */
        $controllers->get('/logout', function() use ($app) {
            /* prevents usage of further pages; it calls $storage->clear(), which
             * is a Symfony class */
            $app['auth']->clearIdentity();

            /* regenerates the session id; also calls clear() */
            $app['session']->invalidate();

            /* Expire the cookie; Symfony2 does not seem to do this by default */
            if(isset($_COOKIE[session_name()])) {
                setcookie(session_name(), "", time() - 3600);
            }

            /* display logged out landing page */
            return $app->redirect($app['url_generator']->generate('after_logged_out'));

        })->bind('do_logout')
          ->requireHttps();

        /**
         *
         */
        $controllers->get('/logged-out', function() use ($app) {
            return $app['twig']->render('index/home.html.twig', array(
                'info' => "You have been logged out",
                'reset_url' => $app['url_generator']->generate('request_reset', array(), true),
                'register_url' => $app['url_generator']->generate('show_registration', array(), true),
            ));

        })->bind('after_logged_out')
          ->requireHttps();

        return $controllers;
    }
}
