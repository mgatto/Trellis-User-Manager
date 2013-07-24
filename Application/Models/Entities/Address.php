<?php

namespace Entities;

use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @Entity
 * @HasLifecycleCallbacks
 * @Table(name="addresses")
 */
class Address implements \ArrayAccess {
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    private $id;

    /**
     * @Column(name="country", type="string", length=96, nullable=true)
     */
    private $country;

    /**
     * @Column(name="state", type="string", length=96, nullable=true)
     */
    private $state;

    /**
     * @Column(name="city", type="string", length=96, nullable=true)
     */
    private $city;

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
     * @OneToOne(targetEntity="Person", inversedBy="address")
     */
    private $person;

    static public function loadValidatorMetadata(ClassMetadata $metadata)
    {
        /* City */
        $metadata->addPropertyConstraint('city', new Assert\NotBlank(array(
            'message' => 'City is required',
            'groups' => array('default'),
        )));

        /* State */
        $metadata->addPropertyConstraint('state', new Assert\NotBlank(array(
            'message' => 'State is required',
            'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('state', new Assert\MinLength(array(
            'limit' => 2,
            'message' => 'State should have at least {{ limit }} characters.',
            'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('state', new Assert\MaxLength(96));
        $metadata->addPropertyConstraint('state', new Assert\Regex(array(
            'pattern' => "/^[a-zA-Z\-\s]*$/",
            'match' => true,
            'message' => 'Must be only lower or uppercase letters, dashes and spaces',
            'groups' => array('default'),
        )));

        /* Country */
        $metadata->addPropertyConstraint('country', new Assert\NotBlank(array(
            'message' => 'Country is required',
            'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('country', new Assert\Country(array(
            'message' => 'Choose a valid country',
            'groups' => array('default'),
        )));
    }

    /**
     *
     * Enter description here ...
     */
    public function __construct() {

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
    public function getCountry()
    {
        return $this->country;
    }

    /**
     *
     * @param $name
     */
    public function setCountry($country)
    {
        $this->country = $country;
    }

    /**
     *
     * @return
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     *
     * @param $notes
     */
    public function setState($state)
    {
        $this->state = $state;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @param $city string
     */
    public function setCity($city)
    {
        $this->city = $city;
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
    public function setPerson(\Entities\Person $person = null) {
        $this->person = $person;
    }

    /**
     *
     * Enter description here ...
     */
    public function getPerson() {
        return $this->person;
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
