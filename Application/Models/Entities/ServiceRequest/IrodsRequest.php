<?php

namespace Entities\ServiceRequest;

use Entities\ServiceRequest;
use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @Entity
 * @HasLifecycleCallbacks
 * @Table(name="irods_requests")
 */
class IrodsRequest extends ServiceRequest implements \ArrayAccess {
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    private $id;

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
     * Specially encrytped irods password, from the user's password
     *
     * This password needs to be passed, encrypted, to the scripts
     * upon registration.
     *
     * @var string
     *
     * @Column(name="password", type="string", type="string", length=50, nullable=false)
     */
    private $password;

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
        $metadata->addPropertyConstraint('password', new Assert\NotBlank());
    }

    /**
     *
     * Enter description here ...
     *
     *
     * @return return_type
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
    public function getPassword()
    {
        return $this->password;
    }

    /**
     *
     * @param $password
     */
    public function setPassword($password)
    {
        /* must escape password since we allow special characters  */
        $escaped_password = escapeshellarg($password);

        /* only the irodsHost is required :-) */
        exec("irodsHost=irods.iplantcollaborative.org /usr/local/bin/iadmin spass {$escaped_password} a9_3fker", $iadmin_output, $command_status);

        /* return status of 0 means success */
        if ( $command_status === 0 ) {
            /* Yes, they really do output a label text, which of course, makes
             * automation in scripts just that much more laborious... */
            $this->password = str_replace('Scrambled form is:', '', $iadmin_output[0]);
        } else {
            /* an attempt to mark it as failed  */
            /*$this->setStatus('failed');
            $this->setNotes("Error: {$iadmin_output[0]}");*/
            throw new \Exception(__CLASS__ . '::' . __METHOD__ .'' . $iadmin_output[0]);
        }
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
