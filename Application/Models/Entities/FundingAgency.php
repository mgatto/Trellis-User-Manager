<?php

namespace Entities;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @Entity(readOnly=true)
 * @Table(name="funding_agencies")
 */
class FundingAgency implements \ArrayAccess {
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    private $id;

    /**
     * @Column(name="name", type="string", length=128)
     */
    private $name;

    /**
     * @Column(name="notes", type="text", nullable=true)
     */
    private $notes;

    /**
     * @var unknown_type
     *
     * @ManyToMany(targetEntity="Institution", mappedBy="funding_agencies")
     */
    private $institutions;

    /**
     *
     * Enter description here ...
     */
    public function __construct() {
        $this->institutions = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Enter description here ...
     * @param unknown_type $institution
     */
    public function setInstitutions($institutions = null) {
        $this->institutions = $institutions;
    }

    /**
     *
     * Enter description here ...
     */
    public function getInstitutions() {
        return $this->institutions;
    }

    /**
     *
     * @param $funding_agency
     */
    public function addInstitution(\Entities\Institution $institution)
    {
        $this->institutions[] = $institution;
        //$institution->addFundingAgency($this);
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
