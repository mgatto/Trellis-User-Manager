<?php
namespace Iplant\Auth\Adapter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManager;
use Iplant\Auth\Adapter\Resolver\DoctrineResolver;

/**
 *
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
 * @copyright 2012 iPlant Collaborative
 * @link
 * @filesource
 */
class BasicAuthAdapter implements \Zend_Auth_Adapter_Interface
{
    /**
     * @var unknown_type
     */
    protected $request;

    /**
     * @var unknown_type
     */
    protected $em;


    /**
     * Class constructor
     *
     * @return void
     */
    public function __construct(Request $request = null, EntityManager $em)
    {
        $this->request = $request;
        $this->em = $em;
    }


    /**
     * (non-PHPdoc)
     * @see Zend_Auth_Adapter_Interface::authenticate()
     */
    public function authenticate()
    {
        $api_key = $_SERVER['PHP_AUTH_USER'];
        $api_secret = $_SERVER['PHP_AUTH_PW'];

        /* now query the database and find the client */
        try {
            /* query TrellisUserApiClient since we only care about authenticating
             * our own API, not future third-party APIs */
            $client = $this->em
                ->getRepository('\Entities\ApiClient\TrellisUserApiClient')
                ->findOneByKey($api_key);
        }
        catch (\Exception $e) {
            //@TODO log it?
            return new \Zend_Auth_Result(
                \Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND,
                null,
                array('Invalid API Key')
            );
        }

        /* check the secret AKA password! */
        if ( $client->getApiSecret() !== $api_secret ) {
            return new \Zend_Auth_Result(
                \Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID,
                null,
                array('Invalid API Secret')
            );
        }

        /* check for approval status */
        if ( 'approved' !== $client->getApproval() ) {
            //@TODO switch among the possible 'approval' values to select the proper text message
            return new \Zend_Auth_Result(
                \Zend_Auth_Result::FAILURE_UNCATEGORIZED,
                null,
                array('API Client not yet Approved for Access')
            );
        }

        /** Is the client coming from the registered IP address? */
        /* handle possible multiple IP Addresses */
        $ip_addresses = explode(',', $client->getIpAddress());

        if ( ! in_array($_SERVER['REMOTE_ADDR'], $ip_addresses) ) {
            return new \Zend_Auth_Result(
                \Zend_Auth_Result::FAILURE_UNCATEGORIZED,
                null,
                array('Unrecognized API Client')
            );
        }

        /* They're authenticated!! */
        return new \Zend_Auth_Result(
            \Zend_Auth_Result::SUCCESS,
            array(
                'id' => $client->getId(),
            ),
            array('Authenticated')
        );

    }
}
