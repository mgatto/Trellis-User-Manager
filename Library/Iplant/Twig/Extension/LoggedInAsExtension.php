<?php

namespace Iplant\Twig\Extension;

/*
 * must add this in controller after initing the TwigExtension:
 * $twig->addExtension(new Iplat_Twig_Extension_LoggedInAsExtension());
 *
 * in template: {% logged_in_as %}
 */
class LoggedInAsExtension extends \Twig_Extension {
    //must get the gloabl $app from TwigEnvironment

    protected $app;

    public function __construct($app) {
        $this->app = $app;
    }

    public function getFunctions()
    {
        return array(
            'logged_in_as' => new \Twig_Function_Method($this, 'getLoggedInAs')
        );
    }

    public function getName()
    {
        return 'iplant_loggedin';
    }

    public function getLoggedInAs()
    {
        /* initialize it so ".=" will not produce a Php Notice */
        $about_me = '';

        if ( $this->app['auth']->hasIdentity() ) {
            $storage = $this->app['auth']->getStorage();
            $data = $storage->read();
            //$data = $this->app['session']->get('auth');

            /* cached in session? */
            if ( ! array_key_exists('person_name', $data) ) {
                $data['person_name'] = $this->getPersonName($data['person_id']);
                $storage->write($data);
            }

            $about_me = '<p id="logged-in-as">';
            $about_me .= 'Logged in as: ' . $data['person_name'];

            /* a logout link next to this seems to be expected by users */
            $about_me .= " &bull; <a href=\"https://xxx.iplantcollaborative.org/cas/logout?service=https://xxx.iplantcollaborative.org/security/logout\">Logout</a>";
            $about_me .= "</p>";
        } else {
            $about_me .= "<a href=\"/dashboard\">Login</a>";
        }

        return $about_me;
    }

    /**
     * Get the full name of the logged-in user
     *
     * @param int $id
     * @return string
     */
    protected function getPersonName($id) {
        if ( empty($id) ) {
            return;
        }

        try {
            $person = $this->app['doctrine.orm.em']
                ->getRepository('Entities\Person')
                ->findOneById($id);
        } catch (Exception $e) {
            /* Doctrine will throw an exception if it can't find the record
             * but, there's nothing to do about it now except to return blank*/
            return;
        }

        return $person->getFirstname() .
               ' ' .
               $person->getLastname();
    }
}
