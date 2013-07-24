<?php

namespace Controllers;

use Symfony\Component\HttpFoundation\Response,
    Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use Silex\Application,
    Silex\ControllerCollection,
    Silex\ControllerProviderInterface;

class UsersController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = new ControllerCollection();

        /* Edit a user */
        $controllers->get('/edit', function() use ($app) {
            /* Security! */
            if ( ! $app['auth']->hasIdentity() ) {
                throw new AccessDeniedHttpException();
            }

            $auth = $app['session']->get('auth');

            $person_object = $app['doctrine.orm.em']
                ->getRepository('\Entities\Person')
                ->findWithAllData($auth['person_id']);

            /* 'account' subform must be removed; leaving it but not displaying it
             * will trigger validation errors upon submit */
            $builder = $app['form.factory']
                ->createBuilder(new \Entities\Person\PersonForm(), $person_object, array(
                    /* CSRF doesn't work in AJAX context, so remove it */
                    'csrf_protection' => false,
                    'validation_groups' => array('update'),
                )
            );
            $builder->remove('account');
            $form = $builder->getForm();

            return $app['twig']->render('users/form.html.twig', array(
                'form' => $form->createView(),
                'action' => $app['url_generator']->generate('update_user', array(), true),
                'reset_url' => '/reset/request',
            ));
        });

        /**
         * Saves form data for a registration
         *
         * Only saves to a database. A back-end Python daemon will grab this data
         * and write to LDAP, create accounts in iRods, etc... so we don't have the
         * apache user getting its fingers into sensitive back-end processes.
         */
        $controllers->post('/update', function() use ($app) {
            /* Security! */
            if ( ! $app['auth']->hasIdentity() ) {
                throw new AccessDeniedHttpException();
            }

            $auth = $app['session']->get('auth');

            /* Filter input! */
            $filter = $app['filter']('StripTags');
            $new_form_values = $filter->filter($app['request']->request->all());
            /* must inject the filtered input back into $request so we can
             * bind() later! */

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

            $app['request']->request->replace($new_form_values);

            /* This special method does not get the Account, since if it did,
             * it would also insist on validating it which cannot happen */
            $person = $app['doctrine.orm.em']
                ->getRepository('\Entities\Person')
                ->findWithAllData($auth['person_id']);

            /* 'account' subform must be removed; leaving it but not displaying it
             * will trigger validation errors upon submit */
            $builder = $app['form.factory']
                ->createBuilder(new \Entities\Person\PersonForm(), $person, array(
                    /* CSRF doesn't work in AJAX context, so remove it */
                    'csrf_protection' => false,
                    'validation_groups' => array('default','update'),
                )
            );
            $builder->remove('account');
            $form = $builder->getForm();

            if ( $app['request']->getMethod() === 'POST' ) {
                /* this is where the person_record is updated with the new form values.
                 * however, it will not save to the database until its
                 * explicitly saved with flush() below. */
                $form->bindRequest($app['request']);

                if ( $form->isValid() ) {
                    $app['doctrine.orm.em']->persist($person);

                    try {
                        /* actual DB write occurs in flush() */
                        $app['doctrine.orm.em']->flush();

                     } catch ( \Exception $e ) {
                         /* very likely that they tried to change thier email to one already in the database */
                         if ( $e->getCode() == '23000' ) {
                             /* re-render the form */
                             $email = $person['emails'][0]['email'];
                             $form->addError(new FormError("This email '{$email}' is already in our system and we do not allow two people to use the same email address."));
                             $form_view = $app['twig']->render('users/form.html.twig', array(
                                 'form' => $form->createView(),
                                 'action' => $app['url_generator']->generate('update_user', array(), true),
                                 'reset_url' => '/reset/request',
                             ));

                             return new Response($form_view, 202);

                         } else {
                            throw $e;
                         }
                     }

                    /* Export the updated data to LOTUS */
                     try {
                         $exporter = new \Iplant\Service\UserExporter\LotusUserExporter('update');
                         $exporter->export($person);

                    } catch (\Exception $e) {
                        /* log it */
                        $app['monolog']->addError(sprintf(
                            "Updating LOTUS for user: '%s' failed: %s", $person->getAccount()->getUsername(), $e->getMessage()
                        ));
                    }

                    //semi-HACKish workaround
                    unset($form);
                    $builder = $app['form.factory']
                        ->createBuilder(new \Entities\Person\PersonForm(), $person, array(
                            /* CSRF doesn't work in AJAX context, so remove it */
                            'csrf_protection' => false,
                            'validation_groups' => array('default','update'),
                        )
                    );
                    $builder->remove('account');
                    $form = $builder->getForm();

                    /* return HTML of results; HTTP 200 is default */
                    return $app['twig']->render('users/form.html.twig', array(
                        'form' => $form->createView(),
                        'action' => $app['url_generator']->generate('update_user', array(), true),
                        'reset_url' => '/reset/request',
                    ));

                } else {
                    /** Invalid data; Re-render the form with errors */
                    $form_view = $app['twig']->render('users/form.html.twig', array(
                        'form' => $form->createView(),
                        'action' => $app['url_generator']->generate('update_user', array(), true),
                        /* hard-coded route, since url_generator doesn't work across controllers (?) */
                        'reset_url' => '/reset/request',
                    ));

                    return new Response($form_view, 202);
                }
            }

            /* render the form since its not a POST (?) */
            return $app['twig']->render('users/form.html.twig', array(
                'form' => $form->createView(),
                'action' => $app['url_generator']->generate('update_user', array(), true),
                /* hard-coded route, since url_generator doesn't work across controllers (?) */
                'reset_url' => '/reset/request',
            ));

        })->bind('update_user')
          ->requireHttps();

        return $controllers;
    }
}
