<?php

namespace Entities;

use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @Entity(repositoryClass="Repositories\ApiClientRepository")
 * @InheritanceType("JOINED")
 * @DiscriminatorColumn(name="subtype", type="string")
 * @DiscriminatorMap({"api_client" = "ApiClient", "trellis_api_client" = "\Entities\ApiClient\TrellisUserApiClient"})
 * @HasLifecycleCallbacks
 * @Table(name="api_clients",indexes={@index(name="client_api_status_idx", columns={"status"}),@index(name="api_client_apikey_idx", columns={"api_key"}),@index(name="api_client_apisecret_idx", columns={"api_secret"})})
 */
class ApiClient implements \ArrayAccess
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    private $id;

    /**
     * @Column(name="name", type="string", length=64, nullable=false)
     */
    private $name;

    /**
     * @Column(name="description", type="text", nullable=true)
     */
    private $description;

    /**
     * IPaddress of Client on its Machine
     *
     * @Column(name="ip_address", type="string", length=83, nullable=true)
     */
    private $ip_address;

    /**
     * @Column(name="url", type="string", length=254, nullable=true)
     */
    private $url;

    /**
     * @Column(name="api_key", type="string", length=40, nullable=false)
     */
    private $api_key;

    /**
     * @Column(name="api_secret", type="string", length=40, nullable=false)
     */
    private $api_secret;

    /**
     * @var string $state
     *
     * @Column(name="status", type="string", length=24, nullable=false)
     */
    private $status = 'add';

    /**
     *
     * Enter description here ...
     * @var unknown_type
     *
     * @Column(name="approval", type="string", length=64, nullable=false)
     */
    private $approval = 'pending';

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
     * One account may have many clients.
     *
     * @ManyToOne(targetEntity="Account", inversedBy="clients")
     */
    private $account;

    /**
     * 1 api may have many clients
     *
     * @ManyToOne(targetEntity="Api", inversedBy="clients", cascade={"all"})
     */
    private $api;

    /**
     *
     * Enter description here ...
     */
    public function __construct()
    {

    }

    static public function loadValidatorMetadata(ClassMetadata $metadata)
    {
        /** Name */
        $metadata->addPropertyConstraint('name', new Assert\NotBlank(array(
            'message' => 'Name is required',
        )));
        $metadata->addPropertyConstraint('name', new Assert\MaxLength(array(
            'limit'  => 64,
        )));

        /* ip addres */
        $metadata->addPropertyConstraint('ip_address', new Assert\MinLength(array(
            'limit'  => 7, /* could be single digit octets (4) + 3 dots */
        )));
        $metadata->addPropertyConstraint('ip_address', new Assert\MaxLength(array(
            'limit'  => 83, /* 5 sets of ip's, each with (full 3-digit octets + 3 dots)  + 4 commas + optional 1 white space after each comma */
        )));

        /* url */
        $metadata->addPropertyConstraint('url', new Assert\Url());

        /* description */
        $metadata->addPropertyConstraint('description', new Assert\NotBlank(array(
            'message' => 'Description is required',
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
    public function getName()
    {
        return $this->name;
    }

    /**
     *
     * @param $name
     */
    public function setName($name)
    {
        $this->name = $name;
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
    public function setIpAddress($ip_address = null)
    {
        $this->ip_address = $ip_address;
    }

    /**
     *
     * @return
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     *
     * @param $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     *
     * @return
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     *
     * @param $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     *
     * @return
     */
    public function getApiKey()
    {
        return $this->api_key;
    }

    /**
     * @param $api_key
     *
     * @PrePersist
     * @PreUpdate
     */
    public function setApiKey($api_key = null)
    {
        /* use random data so we can regenerate a new key later, and
         * not have it be the same... */
        $hashed_name = sha1($this->name . time() . mt_rand(), false);

        /* make a base62 encoded key, since base36 has only a-z0-9; no
         * special chars to be mangled in HTTP headers and its a decent 22 char
         * length. */
        $this->api_key = substr(gmp_strval(gmp_init($hashed_name, 16), 36), 0, 22);
    }

    /**
     * @return string
     */
    public function getApiSecret()
    {
        return $this->api_secret;
    }

    /**
     * If the api_key is updated, the secret must change, too!
     *
     * @PrePersist
     * @PreUpdate
     */
    public function setApiSecret($api_secret = null)
    {
        /* key it to some unique details about the client */
        $identifiers = $this->getName() . $this->getIpAddress() . $this->getUrl();
            // someday, include this, too: $this->getProject()->getName();

        $this->api_secret = hash_hmac('sha1', $identifiers, $this->getApiKey(), false);
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
    public function getApproval()
    {
        return $this->approval;
    }

    /**
     *
     * @param $approval
     */
    public function setApproval($approval)
    {
        $this->approval = $approval;
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
    public function getAccount()
    {
        return $this->account;
    }

    /**
     *
     * @param $owner
     */
    public function setAccount(\Entities\Account $account)
    {
        $this->account = $account;
    }

    /**
     *
     * @return
     */
    public function getApi()
    {
        return $this->api;
    }

    /**
     *
     * @param $api
     */
    public function setApi(\Entities\Api $api)
    {
        $this->api = $api;
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

}
