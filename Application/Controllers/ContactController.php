<?php

namespace Controllers;

use Silex\Application,
    Silex\ControllerCollection,
    Silex\ControllerProviderInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContactController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = new ControllerCollection();

        /**
         * Display the help-via-email form
         */
        $controllers->get('/', function() use ($app) {
            $form = $app['form.factory']
                ->createBuilder(new \Entities\Contact\ContactForm())
                ->getForm();

            /* if logged in, prepopulate the email */
            if ( $auth = $app['session']->get('auth') ) {
                $person = $app['doctrine.orm.em']
                    ->getRepository('\Entities\Person')
                    ->findWithAllData($auth['person_id']);

                $form->setData(array('email' => $person['emails'][0]['email']));
            }

            return $app['twig']->render('contact/form.html.twig', array(
                'form' => $form->createView(),
                'action' => $app['url_generator']->generate('send_help'),
            ));
        });


        /**
         * Send the help email
         */
        $controllers->match('/send', function() use ($app) {
            /* Filter input, even though its coming from CAS */
            $filter = $app['filter']('StripTags');
            $filtered_values = $filter->filter($app['request']->request->all());
            /* must inject filtered input back into $request so we can bind() later! */
            $app['request']->request->replace($filtered_values);

            $form = $app['form.factory']
                ->createBuilder(new \Entities\Contact\ContactForm())
                ->getForm();

            if ($app['request']->getMethod() === 'POST') {
                /* inject the form values into the corresponding entity */
                $form->bindRequest($app['request']);

                /** Main processing */
                if ( $form->isValid() ) {
                    if ( $auth = $app['session']->get('auth') ) {
                        $person = $app['doctrine.orm.em']
                            ->getRepository('\Entities\Person')
                            ->findWithAllData($auth['person_id']);

                    } else {
                        $person = false;
                    }

                    $mail = new \Zend_Mail('UTF-8');
                    $mail->setFrom("reg@iplantcollaborative.org", "Iplant User Manager");

                    /* Zend_Mail can accept an array of recipients */
                    $mail->addTo("support@iplantcollaborative.org");

                    $subject = ($person) ? "{$person->getFirstname()} {$person->getLastname()} Needs Help" : "A User Needs Help";
                    $mail->setSubject("[Trellis] " . $subject);
                    $mail->setBodyText(
                        $app['twig']->render('contact/help_email_to_admin.html.twig', array(
                            'email' => $filtered_values['contact']['email'],
                            'users_ip_address' => $_SERVER['REMOTE_ADDR'],
                            'users_current_url' => $_SERVER['HTTP_REFERER'],
                            'user' => $person,
                            'body' => $filtered_values['contact']['body'],
                    )));

                    /* Zend_Mail_Transport_* throw an Exception if the mail can't be sent */
                    $mail->send();

                    $app['session']->setFlash(
                        'success', "Your help message was successfully sent."
                    );

                    /* send them back to where they came from @TODO replace with AJAX submit in jQuery */
                    return $app->redirect($_SERVER['HTTP_REFERER']);

                } else {
                    /** We haz ewworZ! Gimme da form again so I canz see ewworz.
                     *
                     * But, we also want to return a 404 error to discourage bots */
                    throw new NotFoundHttpException(
                        $app['twig']->render('contact/form.html.twig', array(
                            'form' => $form->createView(),
                            'action' => $app['url_generator']->generate('send_help'),
                            'error' => 'Please check for errors below',
                        ))
                    );
                }
            }

            /** backup for whatever else just might short-curcuit the above */
            return $app['twig']->render('contact/form.html.twig', array(
                'form' => $form->createView(),
                'action' => $app['url_generator']->generate('send_help'),
            ));
        })->method('GET|POST')->bind('send_help');

        return $controllers;
    }
}
