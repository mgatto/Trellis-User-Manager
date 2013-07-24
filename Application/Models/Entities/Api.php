<?php

namespace Entities;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @Entity
 * @HasLifecycleCallbacks
 * @Table(name="apis")
 */
class Api implements \ArrayAccess
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
     * @Column(name="icon", type="string", length=64, nullable=true)
     */
    private $icon;

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
     *
     * @OneToMany(targetEntity="ApiAction", mappedBy="api")
     */
    private $actions;

    /**
     * 1 API has many clients
     *
     * @OneToMany(targetEntity="ApiClient", mappedBy="api", cascade={"persist"})
     */
    private $clients;

    /**
     * 1 api has 1 maintainer; unidirectional
     *
     * @ManyToOne(targetEntity="Maintainer")
     */
    private $maintainer;

    /**
     *
     * Enter description here ...
     */
    public function __construct()
    {
        $this->clients = new ArrayCollection();
        $this->actions = new ArrayCollection();
    }

    static public function loadValidatorMetadata(ClassMetadata $metadata)
    {
        /** Name */
        $metadata->addPropertyConstraint('name', new Assert\NotBlank(array(
            'message' => 'Name is required',
            //'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('name', new Assert\MaxLength(64));

        /* description */
        $metadata->addPropertyConstraint('description', new Assert\NotBlank(array(
            'message' => 'Description is required',
            //'groups' => array('default'),
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
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     *
     * @param $icon
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;
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

    public function addClient(\Entities\ApiClient $client) {
        $this->clients[] = $client;
        $client->setApi($this);
    }

    /**
     *
     * @param $request
     */
    public function addAction(\Entities\ApiAction $action)
    {
        $this->actions[] = $action;
        $action->setApi($this);
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
     * @param $clients
     */
    public function setClients($clients)
    {
        $this->clients = $clients;
    }

    /**
     *
     * @return
     */
    public function getMaintainer()
    {
        return $this->maintainer;
    }

    /**
     *
     * @param $maintainer
     */
    public function setMaintainer(\Entities\Maintainer $maintainer = null)
    {
        $this->maintainer = $maintainer;
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
