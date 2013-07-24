<?php

namespace Entities;

use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @Entity
 * @HasLifecycleCallbacks
 * @Table(name="emails")
 */
class Email implements \ArrayAccess
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    private $id;

    /**
     * @Column(name="name", type="string", length=64, nullable=true)
     */
    private $name;

    /**
     * @Column(name="email", type="string", length=96, unique=true)
     */
    private $email;

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
     * @ManyToOne(targetEntity="Person", inversedBy="emails")
     */
    private $person;

    /**
     *
     * Enter description here ...
     */
    public function __construct() {

    }

    static public function loadValidatorMetadata(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('email', new Assert\NotBlank(array(
            'message' => 'Email is required',
            'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('email', new Assert\Email(array(
            'checkMX' => false,
            'message' => 'Must be a valid email address',
            'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('email', new Assert\MaxLength(array(
            'limit' => 96,
            'groups' => array('default'),
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
    public function getEmail()
    {
        return $this->email;
    }

    /**
     *
     * @param $address
     */
    public function setEmail($email)
    {
        $this->email = $email;
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
