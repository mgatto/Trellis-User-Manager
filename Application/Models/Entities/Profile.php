<?php

namespace Entities;

use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @Entity
 * @HasLifecycleCallbacks
 * @Table(name="profiles",indexes={@index(name="profile_status_idx", columns={"status"})})
 */
class Profile implements \ArrayAccess {
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
     * @Column(name="notes", type="text", nullable=true)
     */
    private $notes;

    /**
     * @ManyToOne(targetEntity="Institution", inversedBy="profile", cascade={"persist","update"})
     */
    private $institution;

    /**
     * @Column(name="department", type="string", length=96, nullable=true)
     */
    private $department;

    /**
     * @ManyToOne(targetEntity="Position")
     */
    private $position;

    /**
     * @ManyToOne(targetEntity="ResearchArea")
     */
    private $research_area;

    /**
     *
     * Enter description here ...
     * @var unknown_type
     *
     * @Column(name="participate_in_survey", type="boolean", nullable=true)
     */
    private $participate_in_survey;

    /**
     *
     * @Column(name="how_heard_about", type="string", nullable=true)
     */
    private $how_heard_about;

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
     * @OneToOne(targetEntity="Person", inversedBy="profile")
     */
    private $person;

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
        /** Department */
        $metadata->addPropertyConstraint('department', new Assert\NotBlank(array(
            'message' => 'Department is required',
            'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('department', new Assert\MaxLength(96));
        $metadata->addPropertyConstraint('department', new Assert\Regex(array(
            //ASCII lower range, only! ...for now, meh.
            'pattern' => "/^[a-zA-Z\/\s-]*$/",
            'match' => true,
            'message' => 'Only English letters, spaces, dashes and / are accepted',
            'groups' => array('default'),
        )));

        /** Position */
        $metadata->addPropertyConstraint('position', new Assert\NotBlank(array(
            'message' => 'Occupation is required',
            'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('position', new Assert\MaxLength(128));
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
    public function getInstitution()
    {
        return $this->institution;
    }

    /**
     *
     * @param $institution
     */
    public function setInstitution(\Entities\Institution $institution = null)
    {
        $this->institution = $institution;
    }

    /**
     *
     * @return
     */
    public function getDepartment()
    {
        return $this->department;
    }

    /**
     *
     * @param $department
     */
    public function setDepartment($department)
    {
        $this->department = $department;
    }

    /**
     *
     * @return
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     *
     * @param $position
     */
    public function setPosition($position)
    {
        $this->position = $position;
    }

    /**
     *
     * @return
     */
    public function getResearchArea()
    {
        return $this->research_area;
    }

    /**
     *
     * @param $research_area
     */
    public function setResearchArea($research_area)
    {
        $this->research_area = $research_area;
    }

    /**
     *
     * @return
     */
    public function getParticipateInSurvey()
    {
        return $this->participate_in_survey;
    }

    /**
     *
     * @param $participate_in_survey
     */
    public function setParticipateInSurvey($participate_in_survey)
    {
        $this->participate_in_survey = $participate_in_survey;
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
    public function getHowHeardAbout()
    {
        return $this->how_heard_about;
    }

    /**
     *
     * @param $how_heard_about
     */
    public function setHowHeardAbout($how_heard_about)
    {
        $this->how_heard_about = $how_heard_about;
    }

    /**
     * @PreUpdate
     */
    function onPreUpdate() {
        // set default status for an update
        $this->status = 'update';
    }
}
