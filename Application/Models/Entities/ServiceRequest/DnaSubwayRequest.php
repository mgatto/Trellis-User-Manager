<?php

namespace Entities\ServiceRequest;

use Entities\ServiceRequest;
use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * We're using Class Table Inheritance
 *
 * @Entity
 * @HasLifecycleCallbacks
 * @Table(name="dnasubway_requests")
 */
class DnaSubwayRequest extends ServiceRequest implements \ArrayAccess {
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    private $id;

    /**
     *
     * Enter description here ...
     * @var unknown_type
     *
     * @Column(name="how_will_use", type="array", length=10000, nullable=false)
     */
    private $how_will_use;

    /**
     *
     * Enter description here ...
     * @var unknown_type
     *
     * @Column(name="school_name", type="string", length=128, nullable=true)
     */
    private $school_name;

    /**
     *
     * Enter description here ...
     * @var unknown_type
     *
     * @Column(name="school_type", type="string", length=64, nullable=true)
     */
    private $school_type;

    /**
     *
     * Enter description here ...
     * @var unknown_type
     *
     * @Column(name="school_surrounding_area", type="string", length=24, nullable=true)
     */
    private $school_surrounding_area;

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
     * Validates the entitiy
     *
     * Used mostly for form-based submissions
     *
     * @param \Symfony\Component\Validator\Mapping\ClassMetadata $metadata
     *
     * @return null
     */
    static public function loadValidatorMetadata(\Symfony\Component\Validator\Mapping\ClassMetadata $metadata)
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
    public function __construct() { }

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
    public function getHowWillUse()
    {
        return $this->how_will_use;
    }

    /**
     *
     * @param $how_will_use
     */
    public function setHowWillUse($how_will_use)
    {
        $this->how_will_use = $how_will_use;
    }

    /**
     *
     * @return
     */
    public function getSchoolName()
    {
        return $this->school_name;
    }

    /**
     *
     * @param $school_name
     */
    public function setSchoolName($school_name)
    {
        $this->school_name = $school_name;
    }

    /**
     *
     * @return
     */
    public function getSchoolType()
    {
        return $this->school_type;
    }

    /**
     *
     * @param $school_type
     */
    public function setSchoolType($school_type)
    {
        $this->school_type = $school_type;
    }

    /**
     *
     * @return
     */
    public function getSchoolSurroundingArea()
    {
        return $this->school_surrounding_area;
    }

    /**
     *
     * @param $school_surrounding_area
     */
    public function setSchoolSurroundingArea($school_surrounding_area)
    {
        $this->school_surrounding_area = $school_surrounding_area;
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
