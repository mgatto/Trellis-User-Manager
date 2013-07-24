<?php

namespace Controllers;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\Response,
    Symfony\Component\Serializer\Serializer;
// use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Validator\Constraints as Assert;

use Silex\Application,
    Silex\ControllerCollection,
    Silex\ControllerProviderInterface;

use Iplant\Service\Notifier;

class RestApiController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = new ControllerCollection();

        $controllers->match('/users/{search_by}/{term}', function ($search_by, $term) use ($app)
        {
            /* Accept only GET for now... */
            if ( ! in_array($app['request']->getMethod(), array('GET','POST','PUT')) ) {
                return new Response(json_encode(array(
                    'error' => array(
                        'HTTP' => 'Method not supported; only GET requests are accepted'),
                    )),
                    405,
                    array('Content-Type' => 'application/json',)
                );
            }

            /** Security! */
            /* this route is automatically switched to use BasicAuthAdapter by the value() at the end */
            $result = $app['auth']->authenticate($app['auth.adapter']);

            if ( ! $result->isValid() ) {
                return new Response(json_encode(array(
                    'error' => array(
                        'Auth' => join('; ', $result->getMessages()),
                    ))),
                    401,
                    array('Content-Type' => 'application/json',)
                );
            }

            if ($app['auth']->hasIdentity()) {
                /** Validate
                 *
                 * We validate here manually so we have more control over the
                 * http code we generate, rather than the ever-present 404 "route not found"
                 * which Symfony\Router produces for assert(). */
                $violation = $app['validator']->validateValue($term, new Assert\MinLength(array(
                    'limit' => 3,
                    'message' => 'Search terms must have at least {{ limit }} characters',
                )));

                if ( count($violation) > 0 ) {
                    $error = array('error' => array('Validation' => $violation[0]->getMessage()));
                    return new Response(json_encode($error), 400, array(
                        'Content-Type' => 'application/json',
                    ));
                }

                /** Limit the results */
                /* set a default; array_merge() depends on string keys to work as we want it to */
                $default_range = array(
                    'start' => 0,
                    'end' => 25,
                );

                try {
                    /* modify if "Range" header specifies a range */
                    if ( $range = $app['request']->headers->get('range') ) {
                        /* break it up by records=0-50 */
                        $parsed_range = explode('=', $range);
                        if ( (! isset($parsed_range[1])) || (empty($parsed_range[1])) ) {
                            /* ERROR! */
                            throw new \LogicException(
                                "Malformed range header. Should be 'Range: records=0-50'"
                            );
                        }

                        /* now get the numbers themselves */
                        $range_parts = explode('-', $parsed_range[1]);
                        if ( (! isset($range_parts[1])) || (empty($range_parts[1])) ) {
                            /* ERROR! */
                            throw new \LogicException(
                                "Malformed range header. Should be 'Range: records=0-50'"
                            );
                        }

                        /* duplicate the structure of $default_range for the sake of array_merge() */
                        $specified_range = array(
                            /* casting is important here, for validation */
                            'start' => (int) $range_parts[0],
                            'end' => (int) $range_parts[1],
                        );

                        $limits = array_merge($default_range, $specified_range);

                    } else {
                        $limits = $default_range;
                    }

                    /* Validate to ensure sane values */
                    switch ( true ) {
                        /* end should never be 0 */
                        case ( $limits['end'] <= 0 ):
                        /* end must always be equal to or greater than start */
                        case ( $limits['end'] < $limits['start'] ):
                            throw new \LogicException(
                                "Invalid range values: start={$limits['start']} and end={$limits['end']}"
                            );
                    }

                } catch (\LogicException $e) {
                    $error = array('error' => $e->getMessage());
                    return new Response(json_encode(array("error"=> array("HTTP" => $error))), 400, array(
                        'Content-Type' => 'application/json',
                    ));
                }

                /** Process according to the type of search */
                try {
                    switch ( $search_by ) {

                        case 'username':
                            $users = $app['doctrine.orm.em']
                                ->getRepository('\Entities\Account')
                                ->findAllByPartialUsername($term);

                            break;

                        case 'name':
                            /* allow whitespace to accomodate typical first name,
                             * last name formatting;
                             * urldecode it because it comes in like this: mike+gatto */
                            $names = preg_split('/\s/', urldecode($term));

                            /* if count > 1, then we have a first name - last name combo */
                            if ( count($names) > 1 ) {
                                /* note the 'And' in the repo function's name! */
                                $users = $app['doctrine.orm.em']
                                    ->getRepository('\Entities\Account')
                                    ->findAllByPartialFirstAndLastName($names[0], $names[1]);
                            }
                            /* otherwise, this single piece may be either a first name or last name: search both */
                            else {
                                $users = $app['doctrine.orm.em']
                                    ->getRepository('\Entities\Account')
                                    ->findAllByPartialFirstOrLastName($names[0]);
                            }

                            break;

                        case 'email':
                            /* must pass in the result limits */
                            $users = $app['doctrine.orm.em']
                                ->getRepository('\Entities\Account')
                                ->findAllByPartialEmailAddress($term, $limits);

                            break;

                        default:
                            throw new \LogicException(
                                "Search type is not recognized: must be one of: username, name, or email"
                            );

                            break;
                    }

                } catch (\LogicException $e ) {
                    $error = array(
                        'error' => array(
                            "Exception" => $e->getMessage()
                    ));
                    return new Response(json_encode($error), 400, array(
                        'Content-Type' => 'application/json',
                    ));

                } catch (\Exception $e) {
                    $json_error = json_encode(array(
                        'error' =>	array("Exception" =>
                            array(
                                'code' => $e->getCode(),
                                'message' => $e->getMessage(),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                                //'trace' => $e->getTraceAsString(),
                            ),
                    )));
                    return new Response($json_error, 500, array(
                        'Content-Type' => 'application/json',
                    ));
                }

                $json = json_encode($users);
                //$json = json_encode(utf8_encode($users));

                // @TODO can use $app->json($data, 200); only in Silex after 30 March 2012
                /* Zend_JSON will call $person's toJson() method if it exists; cool! */
                $json = json_encode(array('users' => $users));
                    // \Zend_Json::prettyPrint()

                if ( empty($users) ) {
                    $code = "404";
                /* 206 is part and parcel of the "Range:" header, to show that its a partial result */
                } elseif ( (count($users) > $limits['end']) || ( $limits['start'] > 0 ) ) {
                    $code = "206";
                /* proper code for a full resultset */
                } else {
                    $code = "200";
                }

                return new Response($json, $code, array(
                    'Content-Type' => 'application/json',
                ));
            }
        })
        ->method('GET|POST|PUT|DELETE|HEAD')
        ->bind('api_users')
        ->requireHttps()
        ->value('auth', 'basic');

        /**
         * Provide a json list of Institution for autocomplete functions
         */
        $controllers->get('/institutions/', function () use($app) {
            /* returns an array, not instance of \Entities\Person */
            $institutions = $app['doctrine.orm.em']
                ->getRepository('\Entities\Institution')
                ->findAllByPartialName($app['request']->get('term'));

            /* Zend_JSON will call $person's toJson() method if it exists; cool! */
            $json = \Zend_Json::encode($institutions);

            return new Response($json, 200, array(
                'Content-Type' => 'application/json',
            ));
        });


        /**
         * List all available iPlant services
         */
        $controllers->get('/services/', function() use($app)
        {
            $services = $app['doctrine.orm.em']
                ->getRepository('\Entities\Service')
                ->findAll();

            $services_list = array("services" => array());
            foreach ($services as $service) {
                $services_list['services'][] = $service->getName();
            }

            /* Zend_JSON will call $person's toJson() method if it exists; cool! */
            $services_json = \Zend_Json::encode($services_list);

            return new Response($services_json, 200, array(
                'Content-Type' => 'application/json',
            ));
        })
        ->bind('api_service_listing')
        ->requireHttps()
        ->value('auth', 'basic');

        /**
         * Modify a service request by an admin
         */
        $controllers->match('/service/{service_name}/{action}/{username}', function($service_name, $action, $username) use($app)
        {
            $action = $app['request']->get('action');
            $username = $app['request']->get('username');
            $service_name = $app['request']->get('service_name');

            /* Only 'add' action is currently supported */
            if (! in_array($action, array('add')) ) {
                throw new \Exception("Action is not permitted");
            }

            /** Find the entities we will operate on */
            try {
                /* findOneByName() is a virtual method */
                $service = $app['doctrine.orm.em']
                    ->getRepository('\Entities\Service')
                    ->findOneByName($service_name);

            } catch (NoResultException $e) {
                return new Response("Service Not Found", 404, array(
                    'Content-Type' => 'text/html',
                ));

            }

            /* findOneByUsername() is defined in AccountRepository.php */
            $account = $app['doctrine.orm.em']
                ->getRepository('\Entities\Account')
                ->findOneByUsername($username);

            /* findOneByUsername() must return boolean instead of a
             * NoResultException...for a variety of reasons. */
            if ( false === $account ) {
                return new Response("User Not Found", 404, array(
                    'Content-Type' => 'text/html',
                ));
            }

            /* Does the user already have the service? Service requests
             * may not exist for a user in certain situations */
            $user_services = $app['doctrine.orm.em']
                ->getRepository('\Entities\Service')
                ->findAllUsersServices($account->getId());

            if ( in_array($service_name, array_keys($user_services)) ) {
                $error = array(
                    'error' => array(
                        "LogicException" => sprintf("User %s already has access to ", $username, $service_name),
                ));
                return new Response(json_encode($message), 400, array(
                    'Content-Type' => 'application/json',
                ));
            }

            /** Is there already an existing service request for this service for this user? */
            $requests = $app['doctrine.orm.em']
                ->getRepository('\Entities\ServiceRequest')
                ->findAllByServiceAndAccount($service->getId(), $account->getId());

            try {
                switch ( true ) {
                    /* we only care when there is 1 or more */
                    case ( count($requests) === 1 ):
                        throw new \LogicException("
                            User {$username} already has requested {$service_name}.
                            Multiple requests not permitted for the same service.
                        ");
                        break;

                    case ( count($requests) > 1 ):
                        throw new \LogicException("
                            Multiple service requests for {$service_name} exist.
                        ");
                        break;

                    default:
                        /* do nothing */
                        break;
                }
            } catch (\LogicException $e ) {
                $error = array(
                    'error' => array(
                        "LogicException" => $e->getMessage()
                ));
                return new Response(json_encode($error), 400, array(
                    'Content-Type' => 'application/json',
                ));
            }

            try {
                switch ( $action ) {
                    case 'add':
                        /** Create a new service request */
                        /* default status is already 'add' for the sake of the daemon */
                        //@TODO handle subtype requests like Atmosphere...
                        $request = new \Entities\ServiceRequest();
                        $request->setAccount($account);
                        $request->setService($service);
                        /* pre-approve it */
                        $request->setApproval('approved');

                        break;

                    default:
                        /* do nothing */
                        break;
                }
            } catch (\Exception $e ) {
                $error = array(
                    'error' => array(
                        "Exception" => sprintf("A database error has occured: %s", $e->getMessage()),
                ));
                return new Response(json_encode($error), 400, array(
                    'Content-Type' => 'application/json',
                ));
            }

            /* Save the service request */
            $app['doctrine.orm.em']->persist($request);
            $app['doctrine.orm.em']->flush();

            return new Response(json_encode(array(
                'service' => array(
                    "Request to {$action} {$service_name} to {$username}'s account was successful."))),
                200,
                array('Content-Type' => 'application/json')
            );
        })
        ->method('POST|PUT')
        ->bind('api_service_requests')
        ->requireHttps()
        ->value('auth', 'basic');


        /**
         * Modify a service request by an admin
         *
         * @TODO this should really be a POST!
         */
        $controllers->get('/request/{action}/{account_id}/{service_id}', function() use($app)
        {
            $action = $app['request']->get('action');
            $account_id = $app['request']->get('account_id');
            $service_id = $app['request']->get('service_id');

            if (! in_array($action, array('approve','deny')) ) {
                throw new \Exception("Action is not permitted");
            }

            /* need this mapping so we can still have semantic verbs in the
             * url and still set the correct status value */
            $actions = array(
                'approve' => 'approved',
                'deny' => 'denied',
            );

            /* find the service request by id */
            try {
                $request = $app['doctrine.orm.em']
                    ->getRepository('\Entities\ServiceRequest')
                    ->findOneByServiceIdAndAccountId($service_id, $account_id);

                $account = $app['doctrine.orm.em']
                    ->getRepository('\Entities\Account')
                    ->findOneById($account_id);

            } catch (NoResultException $e) {
                return new Response("Service Request Not Found", 404, array(
                    'Content-Type' => 'text/html',
                ));

            } catch (NonUniqueResultException $e) {
                return new Response("Multiple service requests found; cannot continue", 404, array(
                    'Content-Type' => 'text/html',
                ));
            }

            /* update its approval */
            $request->setApproval($actions[$action]);

            $app['doctrine.orm.em']->merge($request);
            /* save it */
            $app['doctrine.orm.em']->flush();

            //$body = $app['twig']->render('Hello {{ name }}!', array('name' => 'Fabien'))
            /* email support@iplant... to reduce questions if the request was done or not */
            $notifier = new Notifier('mail', array(
                'recipients' => array('support@iplantcollaborative.org'),
                'subject' => ucwords($actions[$action]) . ": {$account->getUsername()}'s request for {$request->getService()->getName()}",
            ));
            /* no body, just a subject line */
            $notifier->notify(' ');

            return new Response("Service request was successfully {$actions[$action]}", 200, array(
                'Content-Type' => 'text/html',
            ));
        });


        /**
         * Modify a api client registration by an admin
         *
         * @TODO this should really be a POST!
         */
        $controllers->get('/client/{action}/{account_id}/{client_id}', function() use($app)
        {
            $action = $app['request']->get('action');
            $account_id = $app['request']->get('account_id');
            $client_id = $app['request']->get('client_id');

            if (! in_array($action, array('approve','deny')) ) {
                throw new \Exception("Action is not permitted");
            }

            /* need this mapping so we can still have semantic verbs in the
             * url and still set the correct status value */
            $actions = array(
                'approve' => 'approved',
                'deny' => 'denied',
            );

            try {
                /** Is the owner also the logged-in user making the request? */
                $client = $app['doctrine.orm.em']
                    ->getRepository('\Entities\ApiClient')
                    ->findOneByIdAndAccount($client_id, $account_id);
            }
            catch (\Exception $e) {
                throw new \Exception("No such API client was found linked to this account.");
            }

            /* update its approval */
            $client->setApproval($actions[$action]);

            $app['doctrine.orm.em']->merge($client);
            /* save it */
            $app['doctrine.orm.em']->flush();

            /* email support@iplant... to reduce questions if the request was done or not */
            $admin_notifier = new Notifier('mail', array(
                'recipients' => array('support@iplantcollaborative.org'),
                'subject' => ucwords($actions[$action]) . ": {$client->getAccount()->getUsername()}'s API client for '{$client->getApi()->getName()}'",
            ));
            /* no body, just a subject line */
            $admin_notifier->notify(' ');

            /* email the user, too */
            $user_notifier = new Notifier('mail', array(
                'recipients' => array('support@iplantcollaborative.org'),
                'subject' => '[iPlant]' . ucwords($actions[$action]) . ": {$client->getAccount()->getUsername()}'s API client for '{$client->getApi()->getName()}'",
            ));
            /* no body, just a subject line */
            $user_notifier->notify(
                /* html */
                $app['twig']->render('emails/api_client_request_email_to_user.html.twig', array(
                    'person'  => $client->getAccount()->getPerson(),
                    'client'  => $client,
                    'decision' => ucwords($actions[$action]),
                )),
                /* text */
                null
            );

            return new Response("Client registration was successfully {$actions[$action]}", 200, array(
                'Content-Type' => 'text/html',
            ));

        });


        return $controllers;
    }
}
