<?php

namespace Entities;

use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @Entity(repositoryClass="Repositories\InstitutionRepository",readOnly=true)
 *
 * 'readOnly' resolves instances where switching a person's instiution updated its
 * name before reassociating with a new one:

 * @HasLifecycleCallbacks
 * @Table(name="institutions",indexes={@index(name="institution_name_idx", columns={"name"})})
 */
class Institution implements \ArrayAccess {
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    private $id;

    /**
     * @Column(name="name", type="string", length=64)
     */
    private $name;

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
     * @OneToMany(targetEntity="Profile", mappedBy="institution")
     */
    private $profile;

    /**
     *
     * @ManyToMany(targetEntity="FundingAgency", inversedBy="institutions")
     * @JoinTable(name="fundingagency_institution")
     */
    private $funding_agencies;

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
        /** Name */
        $metadata->addPropertyConstraint('name', new Assert\NotBlank(array(
            'message' => 'Institution name is required',
            'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('name', new Assert\MaxLength(array(
            'limit' => 64,
            'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('name', new Assert\Regex(array(
            //ASCII lower range, only! ...for now, meh.
            'pattern' => "/^[a-zA-Z\/\s-]*$/",
            'match' => true,
            'message' => 'Only English letters, spaces, dashes and / are accepted',
            'groups' => array('default'),
        )));
    }

    /**
     *
     * Enter description here ...
     */
    public function __construct() {
        $this->funding_agencies = new \Doctrine\Common\Collections\ArrayCollection();
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


    /**
     *
     * Enter description here ...
     * @param unknown_type $person
     */
    public function setProfile(\Entities\Profile $profile = null) {
        $this->profile = $profile;
    }

    /**
     *
     * Enter description here ...
     */
    public function getProfile() {
        return $this->profile;
    }

    /**
     *
     * @return
     */
    public function getFundingAgencies()
    {
        return $this->funding_agencies;
    }

    /**
     *
     * @param $funding_agencies
     */
    public function setFundingAgencies($funding_agencies)
    {
        $this->funding_agencies = $funding_agencies;
    }

    /**
     *
     * @param $funding_agency
     */
    public function addFundingAgency(\Entities\FundingAgency $funding_agency)
    {
        $this->funding_agencies[] = $funding_agency;
        $funding_agency->addInstitution($this);
    }

    public function __toString() {
        return $this->name;
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
     * @PreUpdate
     */
    function onPreUpdate() {
        // set default status for an update
        $this->status = 'update';
    }
}
