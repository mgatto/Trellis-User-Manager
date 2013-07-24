<?php

namespace Entities;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @Entity(readOnly=true)
 * @Table(name="ethnicities")
 */
class Ethnicity implements \ArrayAccess {
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
     * @var unknown_type
     *
     * @OneToMany(targetEntity="Person", mappedBy="ethnicity")
     */
    private $person;

    /**
     *
     * Enter description here ...
     */
    public function __construct() {}

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
