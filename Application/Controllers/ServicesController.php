<?php

namespace Controllers;

use Silex\Application,
    Silex\ControllerCollection,
    Silex\ControllerProviderInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Iplant\Service\Notifier;

class ServicesController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = new ControllerCollection();

        /**
         * List services
         */
        $controllers->get('/', function() use ($app) {

            /** Security! */
            if ( ! $app['auth']->hasIdentity() ) {
                throw new AccessDeniedHttpException();
            }

            $auth = $app['session']->get('auth');

            /** Query for all possible services */
            $all_services = $app['doctrine.orm.em']
                ->getRepository('\Entities\Service')
                ->findAll();

            $user_services = $app['doctrine.orm.em']
                ->getRepository('\Entities\Service')
                ->findAllUsersServices($auth['user_id']);

            $service_requests = $app['doctrine.orm.em']
                ->getRepository('\Entities\ServiceRequest')
                ->findAllUsersServiceRequests($auth['user_id']);

            /** Filter requests and current user services from all available to avoid duplicates */
            foreach ( $all_services as $i => $service ) {

                /* filter out user services */
                foreach ( $user_services as $owned ) {
                    if ( $owned['name'] === $service['name'] ) {
                        unset($all_services[$i]);

                        /* abort once found and prevent unneeded looping */
                        break;
                    }
                }

                /* filter out requests */
                foreach ( $service_requests as $request ) {

                    if ( $request['service']['name'] === $service['name'] ) {
                        unset($all_services[$i]);

                        /* abort once found and prevent unneeded looping */
                        break;
                    }
                }
            }

            /** Render! */
            return $app['twig']->render("services/index.html.twig",
                array(
                    'services' => $user_services,
                    'requests' => $service_requests,
                    'availables' => $all_services,
                )
            );

        })->requireHttps();

        /** Redirect to proper forms for handling service requests */
        $controllers->get('/request/{id}', function($id) use ($app) {

            /** Security! */
            if ( ! $app['auth']->hasIdentity() ) {
                throw new AccessDeniedHttpException();
            }

            /** We will need metadata about the service being requested */
            $service = $app['doctrine.orm.em']
                ->getRepository('\Entities\Service')
                ->findOneById($id);

            /* check for filename and then for the class */
            $service_name = preg_replace('/\s/', '', ucwords(strtolower($service->getName())));

            /** Does a current/previous request already exist? */
            try {
                /* handle the account/user relation */
                $auth = $app['session']->get('auth');
                $account = $app['doctrine.orm.em']
                    ->getRepository('\Entities\Account')
                    ->findOneById($auth['user_id']);

                $requests = $app['doctrine.orm.em']
                    ->getRepository('\Entities\ServiceRequest')
                    ->findAllByServiceAndAccount($service->getId(), $account->getId());

                switch ( true ) {
                    /* we only care when there is 1 or more */
                    case ( count($requests) === 1 ):
                        throw new \LogicException("
                            A request for {$service_name} already exists.
                            Only one is necessary.
                            If you are experiencing a problem with requesting this service, please contact iPlant Support.
                        ");
                        break;

                    case ( count($requests) > 1 ):
                        throw new \LogicException("
                            Multiple service requests for {$service_name} exist.
                            Please contact iPlant Support to resolve this error.
                        ");
                        break;

                    default:
                        /* do nothing */
                        break;
                }

            } catch (\LogicException $le) {
                throw $le;

            } catch (\Exception $e) {
                $app['monolog']->addError(sprintf(
                    "Database Error: %s", $e
                ));

                /* User-friendly */
                throw new \Exception("A database error has occured; your request could not be processed at this time.");
            }

            /** Can we process it yet? */
            if ( ! file_exists(__DIR__ . "/../Models/Entities/ServiceRequest/{$service_name}RequestForm.php") ) {
                /* No extra form data is needed, so just process it now */
                try {
                    /* handle the service relation */
                    $request = new \Entities\ServiceRequest();
                    $request->setService($service);
                    $request->setAccount($account); /* account is queried for above... */

                    /* Now, save it! */
                    $app['doctrine.orm.em']->persist($request);
                    /* actual DB write occurs in flush() */
                    $app['doctrine.orm.em']->flush();

                } catch (\Exception $e) {
                    // @TODO Not sure I should handle this exception or just ignore it and continue processing
                    throw $e;
                }

                /* Redirect after success */
                $app['session']->setFlash('success', "Your request was successfully added");
                return $app->redirect("/dashboard");
            }
            /* we don't need and else clause since if true, we return out above here */

            /** This service wants extra data collected */
            $action = $app['url_generator']->generate('save_service_request');

            $service_class = "\Entities\ServiceRequest\\{$service_name}Request";
            $service_request = new $service_class();
            $service_request->setId($id);

            $form_class = "\Entities\ServiceRequest\\{$service_name}RequestForm";
            $form = $app['form.factory']->createBuilder(new $form_class(), $service_request)->getForm();

            $view_name = preg_replace('/\s/', '-', strtolower($service->getName()));

            return $app['twig']->render("requests/{$view_name}-form.html.twig", array(
                'form' => $form->createView(),
                'action' => $action
            ));

        })->requireHttps();

        /** Save a service request */
        $controllers->post('/request/save', function() use($app) {
            /** Security! */
            if ( ! $app['auth']->hasIdentity() ) {
                throw new AccessDeniedHttpException();
            }

            $request = $app['request'];
            $auth = $app['session']->get('auth');

            /** Filter input! */
            $filter = $app['filter']('StripTags');
            $new_form_values = $filter->filter($request->request->all());
            /* must inject filtered input back into $request so we can bind() later! */
            $request->request->replace($new_form_values);

            /** Discover service's name and then instantiate it */
            /* get the key since each service will have a different array key */
            $key = key($new_form_values);
            $service = $app['doctrine.orm.em']
                ->getRepository('\Entities\Service')
                ->findOneById($new_form_values[$key]['id']);

            $service_name = preg_replace('/\s/', '', ucwords(strtolower($service->getName())));
            $service_class = "\Entities\ServiceRequest\\{$service_name}Request";
            $service_request = new $service_class();

            /* Determine the corresponding form class and instantiate it */
            $form_class = "\Entities\ServiceRequest\\{$service_name}RequestForm";
            $form = $app['form.factory']->createBuilder(new $form_class(), $service_request)->getForm();

            if ($request->getMethod() == 'POST') {
                $form->bindRequest($request);

                if ( $form->isValid() ) {
                    /** Does a current/previous request already exist? */
                    try {
                        /* handle the account/user relation */
                        $account = $app['doctrine.orm.em']
                            ->getRepository('\Entities\Account')
                            ->findOneById($auth['user_id']);

                        $requests = $app['doctrine.orm.em']
                            ->getRepository('\Entities\ServiceRequest')
                            ->findAllByServiceAndAccount($service->getId(), $account->getId());

                        switch ( true ) {
                            /* we only care when there is 1 or more */
                            case ( count($requests) === 1 ):
                                throw new \LogicException("
                                    A request for {$service_name} already exists.
                                    Only one is necessary.
                                    If you are experiencing a problem with requesting this service, please contact iPlant Support.
                                ");
                                break;

                            case ( count($requests) > 1 ):
                                throw new \LogicException("
                                    Multiple service requests for {$service_name} exist.
                                    Please contact iPlant Support to resolve this error.
                                ");
                                break;

                            default:
                                /* do nothing */
                                break;
                        }

                    } catch (\LogicException $le) {
                        throw $le;

                    } catch (\Exception $e) {
                        $app['monolog']->addError(sprintf(
                            "Database Error: Trying to find any duplicate service requests failed:  %s", $e
                        ));

                        /* User-friendly */
                        throw new \Exception("A database error has occured; your request could not be processed at this time.");
                    }

                    /* No extra form data is needed, so just process it now */
                    try {
                        /* handle the service relation */
                        $service_request->setService($service);

                        /* handle the account/user relation */
                        $account = $app['doctrine.orm.em']
                            ->getRepository('\Entities\Account')
                            ->findOneById($auth['user_id']);

                        $service_request->setAccount($account);

                        /** Now, save it! */
                        $app['doctrine.orm.em']->persist($service_request);
                        $app['doctrine.orm.em']->flush();
                            // actual DB write occurs in flush()

                        /** Email admin since it requires manual approval */
                        unset($new_form_values[$key]['id'], $new_form_values[$key]['_token']);

                        /* only send emails for specific services which absolutely require manual approval */
                        if ( in_array($service_name, array('Atmosphere')) ) {
                            $notifier = new Notifier('mail', array(
                                'recipients' => array('support@iplantcollaborative.org'),
                                'subject' => "{$account->getUsername()} Requests Access to {$key}",
                            ));
                            $notifier->notify(
                                /* html */
                                $app['twig']->render('emails/service_request_email_to_admin.html.twig', array(
                                    'person' => $account->getPerson(),
                                    'request' => $service_request,
                                    'service' => $service,
                                    'data' => $new_form_values,
                                    'key' => $key,
                                )),
                                /* text */
                                $app['twig']->render('emails/service_request_email_to_admin.txt.twig', array(
                                    'person' => $account->getPerson(),
                                    'request' => $service_request,
                                    'service' => $service,
                                    'data' => $new_form_values,
                                    'key' => $key,
                                ))
                            );
                        }

                    } catch (\Exception $e) {
                        /* the service request failed; just redisplay */
                        $action = $app['url_generator']->generate('save_service_request');
                        $view_name = preg_replace('/\s/', '-', strtolower($service->getName()));

                        return $app['twig']->render("requests/{$view_name}-form.html.twig", array(
                            'form' => $form->createView(),
                            'action' => $action,
                            'error' => $e->getMessage(),
                        ));
                    }

                    /* Redirect after success */
                    $app['session']->setFlash('success', "Your request was successfully added");
                    return $app->redirect("/dashboard");
                }

                /* Form was NOT valid, apparently! */
                $action = $app['url_generator']->generate('save_service_request');
                $view_name = preg_replace('/\s/', '-', strtolower($service->getName()));

                return $app['twig']->render("requests/{$view_name}-form.html.twig", array(
                    'form' 	 => $form->createView(),
                    'action' => $action,
                    'error'  => 'Please check the form for errors.'
                ));
            }

        })->bind('save_service_request')
          ->requireHttps();;

        return $controllers;
    }
}
