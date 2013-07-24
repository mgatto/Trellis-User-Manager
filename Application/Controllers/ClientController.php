<?php

namespace Controllers;

use Silex\Application,
    Silex\ControllerCollection,
    Silex\ControllerProviderInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Iplant\Service\Notifier;

/**
 *
 *
 *
 * Usage:
 * <code>
 * </code>
 *
 * PHP version 5
 *
 * @category  project_name
 * @package   package_name
 * @author    Michael Gatto <mgatto@iplantcollaborative.org>
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link
 * @filesource
 */
class ClientController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $controllers = new ControllerCollection();

        /**
         * List all APIs available or in use by the user
         */
        $controllers->get('/', function() use ($app)
        {
            /* Security! */
            if ( ! $app['auth']->hasIdentity() ) {
                throw new AccessDeniedHttpException();
            }

            /** Query for all user's clients */
            $auth = $app['session']->get('auth');

            /** Query for all possible apis */
            $all_apis = $app['doctrine.orm.em']
                ->getRepository('\Entities\Api')
                ->findAll();

            $user_api_clients = $app['doctrine.orm.em']
                ->getRepository('\Entities\ApiClient')
                ->findAllUserClients($auth['user_id']);

            $api_client_requests = $app['doctrine.orm.em']
                ->getRepository('\Entities\ApiClient')
                ->findAllPendingUserClients($auth['user_id']);

            /** Render! */
            return $app['twig']->render("clients/index.html.twig",
                array(
                    'clients' => $user_api_clients,
                    'requests' => $api_client_requests,
                    'availables' => $all_apis,
                    'regenerate' => $app['url_generator']->generate('regenerate_client_keys'),
                )
            );
        })
        ->bind('list_user_api_clients')
        ->requireHttps();


        /**
         *  Redirect to proper forms for handling new client requests
         */
        $controllers->get('/new/{id}', function($id) use ($app)
        {
            /** Security! */
            if ( ! $app['auth']->hasIdentity() ) {
                throw new AccessDeniedHttpException();
            }
            $auth = $app['session']->get('auth');

            /** We will need metadata about the service being requested */
            $api = $app['doctrine.orm.em']
                ->getRepository('\Entities\Api')
                ->findOneById($id);

            $account = $app['doctrine.orm.em']
                ->getRepository('\Entities\Account')
                ->findOneById($auth['user_id']);

            /** All API client registrations will require _some_ data collection */
            $api_name = preg_replace('/\s/', '', ucwords(strtolower($api->getName())));
            $client_class = "\Entities\ApiClient\\{$api_name}Client";
            $client = new $client_class();

            /* handle the API relation */
            $client->setApi($api);

            $form_class = "\Entities\ApiClient\Form\\{$api_name}ClientForm";
            $form = $app['form.factory']->createBuilder(new $form_class(), $client)->getForm();

            $view_name = preg_replace('/\s/', '-', strtolower($api->getName()));

            return $app['twig']->render("clients/{$view_name}-form.html.twig", array(
                'form' => $form->createView(),
                'action' => $app['url_generator']->generate('save_client_registration'),
            ));
        })
        ->bind('new_client')
        ->requireHttps();


        /**
         * Save an Api Client to the database
         *
         * This code is very similar to the structure of code in ServicesController
         */
        $controllers->match('/save', function () use($app)
        {
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
            /* Rebuild the Form for later use */
            $key = key($new_form_values);

            $api = $app['doctrine.orm.em']
                ->getRepository('\Entities\Api')
                ->findOneById($new_form_values[$key]['api']['id']);

            /** All API client registrations will require _some_ data collection */
            $api_name = preg_replace('/\s/', '', ucwords(strtolower($api->getName())));
            $client_class = "\Entities\ApiClient\\{$api_name}Client";
            $client = new $client_class();

            /* handle the API relation */
            $client->setApi($api);

            $form_class = "\Entities\ApiClient\Form\\{$api_name}ClientForm";
            $form = $app['form.factory']->createBuilder(new $form_class(), $client)->getForm();
            $view_name = preg_replace('/\s/', '-', strtolower($api->getName()));

            /* Main Processing */
            if ($request->getMethod() == 'POST') {
                $form->bindRequest($request);

                if ( $form->isValid() ) {
                    /* No extra form data is needed, so just process it now */
                    try {
                        /* handle the account/user relation */
                        $account = $app['doctrine.orm.em']
                            ->getRepository('\Entities\Account')
                            ->findOneById($auth['user_id']);

                        $client->setAccount($account);

                        /** Now, save it! */
                        $app['doctrine.orm.em']->persist($client);
                        /* actual DB write occurs in flush() */
                        $app['doctrine.orm.em']->flush();

                        /* send an email since they require manual approval */
                        $notifier = new Notifier('mail', array(
                            'recipients' => array('support@iplantcollaborative.org'),
                            'subject' => "{$account->getUsername()} Needs Approval for an API Client",
                        ));
                        $notifier->notify(
                            /* html */
                            $app['twig']->render('emails/api_client_request_email_to_admin.html.twig', array(
                                'person' => $account->getPerson(),
                                'client' => $client,
                                'host'   => $_SERVER['SERVER_NAME'],
                            ))
                        );
                    }
                    catch (\Exception $e) {
                        /* the service request failed; just rethrow */
                        return $app['twig']->render("clients/{$view_name}-form.html.twig", array(
                            'form' => $form->createView(),
                            'action' => $app['url_generator']->generate('save_client_registration'),
                            'error' => $e->getMessage(),
                        ));
                    }

                    /* Redirect after success */
                    $app['session']->setFlash('success', "Your client was successfully registered");

                    return $app->redirect('/dashboard');
                } //end if isValid

                /* Not a POST, so just display form nice and quiet, ok. */
                return $app['twig']->render("clients/{$view_name}-form.html.twig", array(
                    'form' => $form->createView(),
                    'action' => $app['url_generator']->generate('save_client_registration'),
                ));
            } //end if post
        })
        ->method('GET|POST')
        ->bind('save_client_registration')
        ->requireHttps();


        /**
         * Regenerate the API client's key and secret
         */
        $controllers->post('/regenerate-keys', function() use ($app)
        {
            /** Security! */
            if ( ! $app['auth']->hasIdentity() ) {
                throw new AccessDeniedHttpException();
            }
            $auth = $app['session']->get('auth');

            /* get the api client entity */
            try {
                /** Is the owner also the logged-in user making the request? */
                $client = $app['doctrine.orm.em']
                    ->getRepository('\Entities\ApiClient')
                    ->findOneByClientIdAndUsername($app['request']->get('client_id'), $auth['name']);
            }
            catch (\Exception $e) {
                throw new \Exception("No such API client was found linked to your account.");
            }

            /* setApiKey() and setApiSecret() are called upon preUpdate().
             * If we just persist without changing a field, it will not actually
             * issue an SQL UPDATE query, and thus change nothing */
            $notes = "[" . date('Y:m:d H:i:s', time()) . "] {$client->getNotes()}Regenerated API key and secret.\r\n";
            $client->setNotes($notes);

            /** Now, save it! */
            $app['doctrine.orm.em']->persist($client);
            /* actual DB write occurs in flush() */
            $app['doctrine.orm.em']->flush();

            /* redirect! */
            return $app->redirect('/dashboard');
        })
        ->bind('regenerate_client_keys')
        ->requireHttps();


        return $controllers;
    }
}
