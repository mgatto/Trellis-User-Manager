<?php
namespace Entities;

use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
//use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @Entity
 * @HasLifecycleCallbacks
 * @Table(name="roles", uniqueConstraints={@UniqueConstraint(name="role_unique_idx", columns={"name"})})
 */
class Role implements \ArrayAccess
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
     * @Column(name="description", type="text", nullable=true)
     */
    private $description;

    /**
     * @ManyToMany(targetEntity="Account", mappedBy="roles")
     * @JoinTable(name="account_role")
     */
    private $account;

    /**
     *
     * Enter description here ...
     */
    public function __construct()
    {

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
    public function getdescription()
    {
        return $this->description;
    }

    /**
     *
     * @param $notes
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return void
     */
    public function setAccount(\Entities\Account $account = null) {
        $this->account = $account;
    }

    /**
     * @return \Entities\Account
     */
    public function getAccount() {
        return $this->account;
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
