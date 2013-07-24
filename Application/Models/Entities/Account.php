<?php
namespace Entities;

use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @Entity(repositoryClass="Repositories\AccountRepository")
 * @HasLifecycleCallbacks
 * @Table(name="accounts",indexes={@index(name="status_idx", columns={"status"})})
 */
class Account implements \ArrayAccess
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    private $id;

    /**
     * @Column(name="username", type="string", length=64, nullable=false, unique=true)
     */
    private $username;

    /**
     * @Column(name="password", type="string", length=64, nullable=false)
     */
    private $password;

    /**
     * Store ip address of request
     * @var unknown_type
     *
     * @Column(name="ip_address", type="string", length=24, nullable=true)
     */
    private $ip_address;

    /**
     * @Column(name="is_validated", type="boolean", nullable=true)
     */
    private $is_validated;

    /**
     * @Column(name="notes", type="text", nullable=true)
     */
    private $notes;

    /**
     * @var datetime $created
     *
     * @Column(type="datetime")
     * @Gedmo\Timestampable(on="create")
     */
    private $created;

    /**
     * @var datetime $updated
     *
     * @Column(type="datetime")
     * @Gedmo\Timestampable(on="update")
     */
    private $updated;

    /**
     * @var string $state
     *
     * @Column(name="status", type="string", length=24, nullable=false)
     */
    private $status = 'add';

    /**
     * @var unknown_type
     *
     * @OneToOne(targetEntity="Person", inversedBy="account", cascade={"persist","delete","merge"})
     */
    private $person;

    /**
     * @ManyToMany(targetEntity="Service", inversedBy="users")
     */
    private $services;

    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     *
     * @OneToMany(targetEntity="ApiClient", mappedBy="account", cascade={"all"})
     */
    private $clients;

    /**
     * @var unknown_type
     *
     * @OneToMany(targetEntity="ServiceRequest", mappedBy="account", cascade={"persist","delete","merge"})
     */
    private $requests;

    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     *
     * @OneToMany(targetEntity="Token", mappedBy="account", cascade={"all"}, orphanRemoval=true)
     */
    private $tokens;

    /**
     *
     * Enter description here ...
     */
    public function __construct() {
        $this->tokens = new ArrayCollection();
        $this->services = new ArrayCollection();
        $this->requests = new ArrayCollection();
        $this->clients = new ArrayCollection();

        $this->setIpAddress();
    }

    static public function loadValidatorMetadata(ClassMetadata $metadata)
    {
        /** Username */
        $metadata->addPropertyConstraint('username', new Assert\NotBlank(array(
            'message' => 'Username is required',
            'groups' => array('registration'),
        )));
        $metadata->addPropertyConstraint('username', new Assert\MinLength(array(
            'limit' => 3,
            'message' => 'Username must have at least {{ limit }} characters',
            'groups' => array('registration'),
        )));
        $metadata->addPropertyConstraint('username', new Assert\MaxLength(array(
            'limit' => 64,
            'groups' => array('registration'),
        )));
        $metadata->addPropertyConstraint('username', new Assert\Regex(array(
            //lowercase ONLY!
            'pattern' => "/^[a-z][a-z\d_-]*$/", // first char = alpha; rest can be alphanumeric and _
            'match' => true,
            'message' => 'First character must be a lowercase letter. Other characters must be only lowercase letters, numbers, dashes and underscores',
            'groups' => array('registration'),
        )));

        $metadata->addGetterConstraint('usernameProhibited', new Assert\False(array(
            'message' => 'Your username is an iPlant reserved word. Please choose another username.',
            'groups' => array('registration'),
        )));

        /** Passwords */
        $metadata->addPropertyConstraint('password', new Assert\NotBlank(array(
            'message' => 'Password is required',
            'groups' => array('registration','reset'),
        )));
        $metadata->addPropertyConstraint('password', new Assert\MinLength(array(
            'limit' => 8,
            'message' => 'Password must have at least {{ limit }} characters',
            'groups' => array('registration','reset'),
        )));
        $metadata->addPropertyConstraint('password', new Assert\MaxLength(array(
            'limit' => 64,
            'message' => 'Password cannot be more than {{ limit }} characters',
            'groups' => array('registration','reset'),
        )));
        $metadata->addPropertyConstraint('password', new Assert\Regex(array(
            'pattern' => '/^.*(?=.*\d+)(?=.*[a-z])(?=.*[A-Z])[0-9a-zA-Z!@#\$%\^&\*\?_~]{8,64}$/', //may need to us the //u[s] modifier to handle utf-8 in regex
            'match' => true,
            'message' => 'Password does not meet strength requirements, or contains special characters we do not accept.',
            'groups' => array('registration','reset'),
        )));
    }

    /**
     *
     * @return
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     *
     * @param $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     *
     * @return
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     *
     * @param $username
     */
    public function setUsername($username)
    {
        $this->username = strtolower($username);
    }

    /**
     * Get the user's password
     *
     * @return
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Set the user's password
     *
     * This algorithm is based on the canonical as specified in RFC...?
     *
     * @param $password
     */
    public function setPassword($password, $encode = false)
    {
        $this->password = $password;

        /* for updates, this should be ok since this->password is bound from
         * the form. Since its newer, it will be in plain text and won't
         * doubley encoded.
         * The $encode parameter is meant for imports from LDAP, since the
         * password is already encoded when coming from there. Before this
         * check, it was doubly encoded and the @PrePersist was forcing it to.
         */
        if ( $encode ) {
            $this->encodePassword();
        }

    }

    /**
     *
     * @return
     */
    public function getIpAddress()
    {
        return $this->ip_address;
    }

    /**
     *
     * @param $ipAddress
     */
    public function setIpAddress($ipAddress = null)
    {
        if ( array_key_exists('REMOTE_ADDR', $_SERVER) ) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }

        $this->ip_address = $ipAddress;
    }

    /**
     *
     * @return
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     *
     * @param $notes
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;
    }
    /**
     *
     * @return
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     *
     * @param $created
     */
    public function setCreated($created)
    {
        $this->created = $created;
    }

    /**
     *
     * @return
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     *
     * @param $updated
     */
    public function setUpdated($updated)
    {
        $this->updated = $updated;
    }

    /**
     *
     * @return
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     *
     * @param $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function setPerson(\Entities\Person $person = null) {
        $this->person = $person;
    }

    public function getPerson() {
        return $this->person;
    }

    /**
     *
     * @param $service
     */
    public function addService(\Entities\Service $service)
    {
        $this->services[] = $service;
        $service->addUser($this);
    }

    /**
     *
     * @return
     */
    public function getServices()
    {
        return $this->services;
    }

    /**
     *
     * @param $service
     */
    public function addRequest(\Entities\ServiceRequest $request)
    {
        $this->requests[] = $request;
        $request->setAccount($this);
    }

    /**
     *
     * @param $services
     */
    public function setRequests($requests)
    {
        $this->requests = $requests;
    }

    /**
     *
     * @return
     */
    public function getRequests()
    {
        return $this->requests;
    }

    /**
     *
     * @param $service
     */
    public function addClient(\Entities\ApiClient $client)
    {
        $this->clients[] = $client;
        $client->setAccount($this);
    }

    /**
     *
     * @param $services
     */
    public function setClients($clients)
    {
        $this->clients = $clients;
    }

    /**
     *
     * @return
     */
    public function getClients()
    {
        return $this->clients;
    }

    /**
     *
     * @param $services
     */
    public function setServices($services)
    {
        $this->services = $services;
    }

    /**
     *
     * @param $token
     */
    public function addToken(\Entities\Token $token)
    {
        $this->tokens[] = $token;
        $token->setAccount($this);
    }

    /**
     *
     * @return
     */
    public function getTokens()
    {
        return $this->tokens;
    }

    /**
     *
     * @param $tokens
     */
    public function setTokens($tokens)
    {
        $this->tokens = $tokens;
    }

    /** Implement ArrayAccess */
    public function offsetExists($offset) {
        return isset($this->$offset);
    }
    public function offsetSet($offset, $value) {
         throw new BadMethodCallException("Array access of class " . get_class($this) . " is read-only!");
    }
    public function offsetGet($offset) {
        return $this->$offset;
    }
    public function offsetUnset($offset) {
         throw new BadMethodCallException("Array access of class " . get_class($this) . " is read-only!");
    }

    /**
     *
     * @return
     */
    public function getIsValidated()
    {
        return $this->is_validated;
    }

    /**
     *
     * @param $is_validated
     */
    public function setIsValidated($is_validated)
    {
        $this->is_validated = $is_validated;
    }

    /**
     * Encodes the password to format acceptable to SLAPD.
     *
     * @param string The password in plain text
     *
     * @return string The password encoded for LDAP SSHA format
     *
     * A lifecycle callback is required since before, this code was called BEFORE
     * validation, which caused the regex to fail.
     *
     * @PrePersist
     * @PreUpdate
     */
    public function encodePassword() {
        /* if the password is still LDAP formatted, don't redo it, silly! */
        if ( '{SSHA}' === substr($this->password, 0, 6) ) {
            return $this->password;
        }

        /** Generate a salt */
        /* the full range from which to select chars in the salt */
        $list = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        /* init the string so appending later doesn't raise a PHP_WARN or PHP_NOTICE */
        $salt = "";
        /* generate a 4 char long random string; the sample python says 16 char, but all
         * of the passwords generated by slappasswd are 32 char and not 48 like that one's */
        for ($i = 0; $i < 4; $i++) {
            /* seed the randomness sufficiently */
            mt_srand( (double) microtime()*1000000 );

            /* select a string from the list */
            $index = mt_rand(0, strlen($list) - 1);
            $salt .= $list[$index];
        }

        /** Generate the password */
        /* sha1 must return a binary representation and not hex = 2nd
         * param = true. */
        $sha_1 = sha1($this->password . $salt, true);

        /* {SSHA} is a mandatory prefix so OpenLDAP knows which schema the
         * password uses.
         * must (?) be 32 chars not including {SSHA}? */
        $this->password = "{SSHA}" . base64_encode($sha_1 . $salt);
    }

    /**
     * Create a temp password
     *
     * Temp password is assigned to accomodate old system where user had to use
     * a web script to set their password. Passwords are thus blank when querying
     * for such accounts from LDAP presently. This will not be the case after
     * a while of using the User Portal. CRC32 is used to shorten the string
     * to something more human memorable and DECHEX to make it a mixed alpha-numeric
     * string.
     *
     * @param string $seed Unimplemented
     *
     * @return string An uppercase hex-formatted random string
     */
    public function createTempPassword($seed = null) {
        return strtoupper(
            dechex(
                crc32(
                    md5(mt_rand() . microtime(), false)
                )
            )
        );
    }

    /**
     * A Symfony2 Validator callback
     *
     * Checks username against an array of forbidden usernames
     *
     * @return boolean
     */
    public function isUsernameProhibited() {
        $prohibited_usernames = array(
            'admin', 'admin2', 'administrator', 'admin_proxy', 'andye', 'apache', 'atmosphere', 'atmo_notify', 'bisque', 'confluence', 'condor', 'de', 'de-irods', 'edwin', 'eucalyptus', 'ipc_admin', 'iplant', 'iplant_user', 'iplantadmin', 'irods_monitor', 'jira', 'jiracli', 'ipcservices', 'manager', 'monitor_user', 'mysql', 'nagios', 'nagiosadmin', 'nobody', 'postgres', 'proxy-de-tools', 'public', 'puppet', 'quickshare', 'rods', 'rodsadmin', 'rodsBoot', 'rodsuser', 'root', 'systems', 'tomcat', 'tnrs', 'user_management', 'world'
        );

        return in_array($this->username, $prohibited_usernames);
    }
}
