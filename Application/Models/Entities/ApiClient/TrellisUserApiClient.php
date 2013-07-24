<?php

namespace Entities\ApiClient;

use Entities\ApiClient;
use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @Entity(repositoryClass="Repositories\TrellisUserApiClientRepository")
 * @HasLifecycleCallbacks
 * @Table(name="trellis_api_clients")
 */
class TrellisUserApiClient extends ApiClient implements \ArrayAccess
{
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
     *
     * Enter description here ...
     * @var string
     *
     * @Column(name="how_will_use", type="text", nullable=false)
     */
    private $how_will_use;

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
        $metadata->addPropertyConstraint('how_will_use', new Assert\NotBlank());
    }

    /**
     *
     * Enter description here ...
     *
     *
     * @return return_type
     */
    public function __construct()
    {

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
