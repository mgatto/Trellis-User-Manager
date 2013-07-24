<?php

namespace Entities;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @Entity(repositoryClass="Repositories\TokenRepository")
 * @HasLifecycleCallbacks
 * @Table(name="tokens", indexes={@index(name="token_idx", columns={"token"})})
 */
class Token implements \ArrayAccess {
    /**
     * @Id
     * @Column(name="id", type="integer", nullable=false))
     * @GeneratedValue
     */
    private $id;

    /**
     * @Column(name="token", type="string", length=32)
     */
    private $token;

    /**
     * @Column(name="purpose", type="string", length=64)
     */
    private $purpose;

    /**
     * @Column(name="expiration", type="datetime")
     */
    private $expiration;

    /**
     * Store ip address of request
     * @var unknown_type
     *
     * @Column(name="ip_address", type="string", length=24, nullable=true)
     */
    private $ip_address;

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
     * @ManyToOne(targetEntity="Account", inversedBy="tokens")
     */
    private $account;

    /**
     * Validates the entitiy
     *
     * Used mostly for form-based submissions
     *
     * @param ClassMetadata $metadata
     *
     * @return null
     */
    static public function loadValidatorMetadata(ClassMetadata $metadata)
    {
        /** Token */
        $metadata->addPropertyConstraint('token', new Assert\NotNull());
        $metadata->addPropertyConstraint('token', new Assert\NotBlank());
        $metadata->addPropertyConstraint('token', new Assert\MinLength(32));
        $metadata->addPropertyConstraint('token', new Assert\MaxLength(32));

        /** Token expiration */

        /** Token purpose */
        $metadata->addPropertyConstraint('purpose', new Assert\Callback(function(ExecutionContext $context){
            $purpose = $this->getPurpose();
            if ( ! in_array($purpose, array('validation', 'registration', 'reset', 'password')) ) {
                //@TODO what does this do?
                $property_path = $context->getPropertyPath() . '.purpose';
                $context->setPropertyPath($property_path);
                $context->addViolation('The purpose for setting this token is not valid', array(), null);
            }
        }));
    }

    /**
     *
     * Enter description here ...
     */
    public function __construct($purpose = 'validation') {
        $this->setPurpose($purpose);
        $this->setToken();
        $this->setExpiration();

        $this->setIpAddress();
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
    public function getToken()
    {
        return $this->token;
    }

    /**
     *
     * @param $token
     */
    public function setToken($token = null)
    {
        $this->token = strtoupper(md5(rand() . microtime(), false));
    }

    /**
     *
     * @return
     */
    public function getPurpose()
    {
        return $this->purpose;
    }

    /**
     *
     * @param $purpose
     */
    public function setPurpose($purpose)
    {
        $this->purpose = $purpose;
    }

    /**
     *
     * @return
     */
    public function getExpiration()
    {
        return $this->expiration;
    }

    /**
     *
     * @param $expiration
     */
    public function setExpiration($expiration = null)
    {
        if ( null !== $expiration ) {
            /* $expiration is a Php5.3 DateTime instance */
            $date = $expiration;
        } else {
            $date = new \DateTime('now');
            /* Add 7 days to now to get expiration.
             * See http://www.php.net/DateInterval for date formats */
            $date->add(new \DateInterval('P14D'));
        }

        $this->expiration = $date;
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
        if ( empty($ipAddress) ) {
            $this->ip_address = $_SERVER['REMOTE_ADDR'];
        } else {
            $this->ip_address = $ipAddress;
        }
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
     * @param $account
     */
    public function setAccount(\Entities\Account $account = null)
    {
        $this->account = $account;
    }

    /**
     * Convert this object to a usable string representation
     *
     * @return string
     */
    public function __toString() {
        return $this->token;
    }

    /**
     * @PreUpdate
     */
    function onPreUpdate() {
        // set default status for an update
        $this->status = 'update';
    }
}
