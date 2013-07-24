<?php

namespace Entities;

use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @Entity
 * @Table(name="service_actions",indexes={@index(name="service_action_event_idx", columns={"event"})})
 */
class ServiceAction implements \ArrayAccess {
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    private $id;

    /**
     * @var string
     * @Column(name="event", type="string", length=96, nullable=true)
     */
    private $event;

    /**
     * @var string
     * @Column(name="action", type="string", length=96, nullable=true)
     */
    private $action;

    /**
     * @var string
     * @Column(name="url", type="string", length=255, nullable=true)
     */
    private $url;

    /**
     * @Column(name="notes", type="text", nullable=true)
     */
    private $notes;

    /**
     * Many requests per 1 service
     *
     * @ManyToOne(targetEntity="Service", inversedBy="actions")
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
     * @return
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     *
     * @return
     */
    public function getAction()
    {
        return $this->action;
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
     * @return
     */
    public function getNotes()
    {
        return $this->notes;
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
     * @param $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     *
     * @param $event
     */
    public function setEvent($event)
    {
        $this->event = $event;
    }

    /**
     *
     * @param $action
     */
    public function setAction($action)
    {
        $this->action = $action;
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
     * @param $notes
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;
    }

    /**
     *
     * @param $service
     */
    public function setService(\Entities\Service $service = null)
    {
        $this->service = $service;
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
