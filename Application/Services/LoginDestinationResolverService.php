<?php
namespace Services;

/**
 * Determine where SecurityController should send a valid user after login.
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
 * @copyright 2012 iPlant Collaborative
 * @link
 * @filesource
 */
class LoginDestinationResolverService {

    /**
     * Role to URL mapping
     */
    protected $destinations;

    /**
     * Class constructor
     */
    public function __construct()
    {
        //@TODO would be nice to have something like:
        // workshop.administrator
        // and workshop.trainer

        $this->destinations = array(
            'Implementor'		=> '/admin',
            'Administrator'		=> '/admin',
            'Workshop.Operator'	=> '/admin/workshops',
            //@TODO how to handle more than one worksop they are the trainer for?
            'Workshop.Trainer'	=> '/admin/workshop/{workshop}',
            'User'				=> '/services',
            'Everyone'			=> '/', //Probably will not ever be needed...
        );
    }

    /**
     * Return a user's post-login destination based on the highest role
     * they possess.
     *
     * @param mixed $identity   From Zend_Auth
     * @param string $strategy  Flag to decide how to route the user
     *
     * @return string
     */
    public function getLoginDesintation(Array $identity, $strategy = 'highest_role')
    {
        /* find the highest role the user has */
        $allowable_urls = array_intersect(array_keys($this->destinations), $identity['roles']);
        //$highest_role = ;

        return $this->destinations[reset($allowable_urls)];
    }

}
