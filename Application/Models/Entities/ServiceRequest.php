<?php

namespace Entities;

use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @Entity(repositoryClass="Repositories\ServiceRequestRepository")
 * @InheritanceType("JOINED")
 * @DiscriminatorColumn(name="subtype", type="string")
 * @DiscriminatorMap({"service_request" = "ServiceRequest", "dna_subway_request" = "\Entities\ServiceRequest\DnaSubwayRequest", "atmosphere_request" = "\Entities\ServiceRequest\AtmosphereRequest", "irods_request" = "\Entities\ServiceRequest\IrodsRequest"})
 * @HasLifecycleCallbacks
 * @Table(name="service_requests",indexes={@index(name="service_request_status_idx", columns={"status"})})
 */
class ServiceRequest implements \ArrayAccess {
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    private $id;

    /**
     * Store ip address of request
     * @var unknown_type
     *
     * @Column(name="ip_address", type="string", length=24, nullable=true)
     */
    private $ip_address;

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
     * @var string $state
     *
     * @Column(name="status", type="string", length=24, nullable=false)
     */
    private $status = 'add';

    /**
     * @var Entities\Account
     *
     * @ManyToOne(targetEntity="Account", inversedBy="requests")
     */
    private $account;

    /**
     * Many requests per 1 service (?)
     *
     * @ManyToOne(targetEntity="Service", inversedBy="requests")
     */
    private $service;

    /**
     * Validates the entitiy
     *
     * Used mostly for form-based submissions
     *
     * @param \Symfony\Component\Validator\Mapping\ClassMetadata $metadata
     *
     * @return null
     */
    static public function loadValidatorMetadata(ClassMetadata $metadata)
    {
        //$metadata->addPropertyConstraint('notes', new Assert\MaxLength(64));
        //$metadata->addPropertyConstraint('type', new Assert\MaxLength(96));
    }

    /**
     *
     * Enter description here ...
     *
     *
     * @return return_type
     */
    public function __construct() {
        $this->setIpAddress();
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
    public function getAccount()
    {
        return $this->account;
    }

    /**
     *
     * @param $service
     */
    public function setService(\Entities\Service $service = null)
    {
        $this->service = $service;
    }

    /**
     *
     * @return
     */
    public function getService()
    {
        return $this->service;
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
