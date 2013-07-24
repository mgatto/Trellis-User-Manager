<?php

namespace Entities;

use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @Entity(repositoryClass="Repositories\PersonRepository")
 * @HasLifecycleCallbacks
 * @Table(name="people",indexes={@index(name="person_status_idx", columns={"status"}), @index(name="first_name_idx", columns={"first_name"}), @index(name="last_name_idx", columns={"last_name"}) })
 */
class Person implements \ArrayAccess {
    /**
     * @Id
     * @Column(name="id", type="integer", nullable=false))
     * @GeneratedValue
     */
    private $id;

    /**
     * @Column(name="first_name", type="string", length=64)
     */
    private $firstname;

    /**
     * @Column(name="last_name", type="string", length=64)
     */
    private $lastname;

    /**
     * Gender of a person
     *
     * @Column(name="gender", type="string", length=12, nullable=true)
     *
     * @var string
     */
    private $gender;

    /**
     * 2 Letter code; added 2 more chars to accomodate 'None' if needed.
     *
     * @Column(name="citizenship", type="string", length=4, nullable=true)
     */
    private $citizenship;

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
     * @var \Doctrine\Common\Collections\ArrayCollection
     *
     * @OneToMany(targetEntity="Email", mappedBy="person", cascade={"all"})
     */
    private $emails;

    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     *
     * @OneToMany(targetEntity="Phonenumber", mappedBy="person", cascade={"all"})
     */
    private $phonenumbers;

    /**
     * @var \Doctrine\Common\Collections\ArrayCollection
     *
     * @OneToMany(targetEntity="Faxnumber", mappedBy="person", cascade={"all"})
     */
    private $faxnumbers;

    /**
     * @var unknown_type
     *
     * @ManyToOne(targetEntity="Ethnicity", inversedBy="person", cascade={"persist","update"})
     */
    private $ethnicity;

    /**
     * @var unknown_type
     *
     * @OneToOne(targetEntity="Address", mappedBy="person", cascade={"all"})
     */
    private $address;

    /**
     * @var unknown_type
     *
     * @OneToOne(targetEntity="Profile", mappedBy="person", cascade={"all"})
     */
    private $profile;

    /**
     * @var unknown_type
     *
     * @OneToOne(targetEntity="Account", mappedBy="person", cascade={"all"})
     */
    private $account;

    static public function loadValidatorMetadata(ClassMetadata $metadata)
    {
        /** Firstname */
        $metadata->addPropertyConstraint('firstname', new Assert\NotBlank(array(
            'message' => 'First Name is required',
            'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('firstname', new Assert\MinLength(array(
            'limit' => 2,
            'message' => 'First name must have at least {{ limit }} characters',
            'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('firstname', new Assert\MaxLength(64));
        $metadata->addPropertyConstraint('firstname', new Assert\Regex(array(
            'pattern' => "/^[a-zA-Z'\-\s]*$/",
            'match' => true,
            'message' => 'Must be only lower or uppercase English letters, dashes, spaces and apostrophes',
            'groups' => array('default'),
        )));

        /** Lastname */
        $metadata->addPropertyConstraint('lastname', new Assert\NotBlank(array(
            'message' => 'Last Name is required',
            'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('lastname', new Assert\MinLength(array(
            'limit' => 2,
            'message' => 'Last name must have at least {{ limit }} characters',
            'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('lastname', new Assert\MaxLength(64));
        $metadata->addPropertyConstraint('lastname', new Assert\Regex(array(
            'pattern' => "/^[a-zA-Z'\-\s]*$/",
            'match' => true,
            'message' => 'Must be only lower or uppercase English letters, dashes, spaces and apostrophes',
            'groups' => array('default'),
        )));

        /** Gender */
        $metadata->addPropertyConstraint('gender', new Assert\NotBlank(array(
            'message' => 'Gender is required',
            'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('gender', new Assert\Choice(array(
            'choices' => array('male', 'female', 'declined'),
            'message' => 'Choose a Gender',
            'groups' => array('default'),
        )));

        /* Citizenship */
        $metadata->addPropertyConstraint('citizenship', new Assert\NotBlank(array(
            'message' => 'Citizenship is required',
            'groups' => array('default'),
        )));
        $metadata->addPropertyConstraint('citizenship', new Assert\Country(array(
            'message' => 'Choose a valid country',
            'groups' => array('default'),
        )));

        /* Ethnicity */
        $metadata->addPropertyConstraint('ethnicity', new Assert\NotBlank(array(
            'message' => 'Ethnicity is Required',
            'groups' => array('default'),
        )));
        /* An entity validator is automatically attached; we don't nned to specify
         * as we did with choice and country types above. */

        /* ensures validations reaches into deep object graphs ?? */
        $metadata->addPropertyConstraint('account', new Assert\Valid());
        $metadata->addPropertyConstraint('emails', new Assert\Valid());
        $metadata->addPropertyConstraint('address', new Assert\Valid());
        $metadata->addPropertyConstraint('faxnumbers', new Assert\Valid());
        $metadata->addPropertyConstraint('phonenumbers', new Assert\Valid());
    }

    /**
     *
     * Enter description here ...
     */
    public function __construct() {
        //@TODO we might be able to remove these by $form->bind($person_with_no_data)
        // constructed from a full person object with all members instantiated?
        $this->emails = new ArrayCollection();
        $this->phonenumbers = new ArrayCollection();
        $this->faxnumbers = new ArrayCollection();

        //Collection types of elements apparently need blank fields added
        $this->addFaxnumber(new \Entities\Faxnumber());
        $this->addEmail(new \Entities\Email());
        $this->addPhonenumber(new \Entities\Phonenumber());
        $this->setAccount(new \Entities\Account());
        $this->setProfile(new \Entities\Profile());
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
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     *
     * @param $firstname
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;
    }

    /**
     *
     * @return
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     *
     * @param $lastname
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;
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
    * Set the collection of related emails
    *
    * @param \Doctrine\Common\Collections\ArrayCollection $emails
    */
    public function setEmails(\Entities\Email $emails = null) {
        $this->emails = $emails;
        $emails->setPerson($this);
    }

    public function getEmails() {
        return $this->emails;
    }

    /**
     * Add an email to the collection of related emails
     *
     * @param \Entity\Email $email
     */
    public function addEmail(\Entities\Email $email)
    {
        $this->emails->add($email);
        $email->setPerson($this);
    }

    /**
     * Remove an email to the collection of related emails
     *
     * @param \Entity\Email $email
     */
    public function removeEmail(\Entities\Email $email)
    {
        $this->emails->removeElement($email);
    }

    /**
     * Set the collection of related phonenumbers
     *
     * @param \Doctrine\Common\Collections\ArrayCollection $phonenumbers
     */
    public function setPhonenumbers(\Entities\Phonenumber $phonenumbers = null) {
        $this->phonenumbers = $phonenumbers;
    }

    public function getPhonenumbers() {
        return $this->phonenumbers;
    }

    /**
     * Add a phonenumber to the collection of related phonenumbers
     *
     * @param \Entity\Phonenumber $phonenumber
     */
    public function addPhonenumber(\Entities\Phonenumber $phonenumber)
    {
        $this->phonenumbers->add($phonenumber);
        $phonenumber->setPerson($this);
    }

    /**
     * Remove a phonenumber to the collection of related phonenumbers
     *
     * @param \Entity\Phonenumber $phonenumber
     */
    public function removePhonenumber(\Entities\Phonenumber $phonenumber)
    {
        $this->phonenumbers->removeElement($phonenumber);
    }

    /**
     * Set the collection of related faxnumbers
     *
     * @param \Doctrine\Common\Collections\ArrayCollection $faxnumbers
     */
    public function setFaxnumbers(\Entities\Faxnumber $faxnumbers = null) {
        $this->faxnumbers = $faxnumbers;
    }

    public function getFaxnumbers() {
        return $this->faxnumbers;
    }

    /**
     * Add a faxumber to the collection of related faxnumbers
     *
     * @param \Entity\Faxnumber $faxnumber
     */
    public function addFaxnumber(\Entities\Faxnumber $faxnumber)
    {
        $this->faxnumbers->add($faxnumber);
        $faxnumber->setPerson($this);
    }

    /**
     * Remove a faxnumber to the collection of related faxnumbers
     *
     * @param \Entity\Faxnumber $faxnumber
     */
    public function removeFaxnumber(\Entities\Faxnumber $phonenumber)
    {
        $this->faxnumbers->removeElement($phonenumber);
    }

    /**
     *
     * @return
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     *
     * @param $address
     */
    public function setAddress(\Entities\Address $address = null)
    {
        $this->address = $address;
        $address->setPerson($this);
    }

    /**
     *
     * @return
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     *
     * @param $profile
     */
    public function setProfile(\Entities\Profile $profile = null)
    {
        $this->profile = $profile;
        $profile->setPerson($this);
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
     * @param $account
     */
    public function setAccount(\Entities\Account $account = null)
    {
        $this->account = $account;
        $account->setPerson($this);
    }

    public function __toString() {
        return $this->firstname . ' ' . $this->lastname;
    }

    /**
     *
     * @return
     */
    public function getEthnicity()
    {
        return $this->ethnicity;
    }

    /**
     *
     * @param $ethnicity
     */
    public function setEthnicity(\Entities\Ethnicity $ethnicity)
    {
        $this->ethnicity = $ethnicity;
    }

    /**
     *
     * @return
     */
    public function getGender()
    {
        return $this->gender;
    }

    /**
     *
     * @param $gender
     */
    public function setGender($gender)
    {
        $this->gender = $gender;
    }

    /**
     * @return string
     */
    public function getCitizenship()
    {
        return $this->citizenship;
    }

    /**
     * @param string $citizenship
     */
    public function setCitizenship($citizenship)
    {
        $this->citizenship = $citizenship;
    }


    /**
     * @PreUpdate
     */
    function onPreUpdate() {
        // set default status for an update
        $this->status = 'update';
    }
}
