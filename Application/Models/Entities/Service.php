<?php

namespace Entities;

use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @Entity(repositoryClass="Repositories\ServiceRepository")
 * @HasLifecycleCallbacks
 * @Table(name="services")
 */
class Service implements \ArrayAccess
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    private $id;

    /**
     * @Column(name="name", type="string", length=96, nullable=false)
     */
    private $name;

    /**
     * @Column(name="icon", type="string", length=64, nullable=true)
     */
    private $icon;

    /**
     * @Column(name="description", type="text", nullable=true)
     */
    private $description;

    /**
     * @var string
     * @Column(name="type", type="string", length=96, nullable=true)
     */
    private $type;

    /**
     * @Column(name="notes", type="text", nullable=true)
     */
    private $notes;

    /**
     * @Column(name="is_running", type="boolean", nullable=true)
     */
    private $isRunning = 1;

    /**
     * A transient property which overrides $isRunning
     *
     * @Column(name="maintenance_message", type="text", nullable=true)
     */
    private $maintenanceMessage;

    /**
     * @var Entities\Account
     *
     * @ManyToMany(targetEntity="Account", mappedBy="services")
     * @JoinTable(name="account_service")
     */
    private $users;

    /**
     * one service may have many requests
     *
     * @OneToMany(targetEntity="ServiceRequest", mappedBy="service")
     */
    private $requests;

    /**
     * 1 service has 1 maintainer; unidirectional
     *
     * @ManyToOne(targetEntity="Maintainer")
     */
    private $maintainer;

    /**
     *
     * @OneToMany(targetEntity="ServiceAction", mappedBy="service")
     */
    private $actions;

    /**
     * Validates the entitiy
     *
     * Used mostly for form-based submissions
     *
     * @param \Symfony\Component\Validator\Mapping\ClassMetadata $metadata
     *
     * @return null
     */
    static public function loadValidatorMetadata(\Symfony\Component\Validator\Mapping\ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('name', new Assert\MinLength(2));
        $metadata->addPropertyConstraint('name', new Assert\MaxLength(64));
        $metadata->addPropertyConstraint('icon', new Assert\MaxLength(64));
        $metadata->addPropertyConstraint('type', new Assert\MaxLength(96));
        $metadata->addPropertyConstraint('url', new Assert\Url());
    }

    /**
     * (PostLoad to re-enable add @ in front of it)
     *
     * @return return_type
     */
    public function getServiceStateFromWebservice()
    {
        /* get the status of the service */
        $name = $this->getName();
        $score = 0;

        try {
            $client = new \Guzzle\Service\Client('http://xxx.iplantcollaborative.org/nagiosapi/services/');
            $request = $client->get(urlencode($name));
            $response = $request->send();

            $body = $response->getBody(true);
            $service_data = json_decode($body, true);

            /* no data found about the service, but the json produces an array
             * with one element whose value is null, so empty() does not catch it.
             * since isRunning's default is true; let it remain  */
            if ( (isset($service_data[0])) && (is_null($service_data[0])) )  {
                return $this->isRunning = 1;
            }
        } catch (Exception $e) {
            /* since isRunning's default is true; let it remain */
            return $this->isRunning = 1;
        }

        /* Get the number of running instances with a status of 0 */
        foreach ($service_data as $ip => $service) {
            foreach ($service as $id => $attributes) {
                /* we purposefully won't do strict matching here, since its '0' string in JSON but may be auto-converted in a php array */
                if ($attributes['service_current_state'] == 0) {
                    $score++;
                }
            }
        }

        /* if 0 -> false; any non-negative number -> true (for sure?) */
        $this->isRunning = $score;
    }

    /**
     *
     * Service constructor
     *
     *
     * @return return_type
     */
    public function __construct() {
        $this->users = new ArrayCollection();
        $this->requests = new ArrayCollection();
        $this->actions = new ArrayCollection();
    }

    /**
     *
     * @param \Entities\Account $user
     */
    public function addUser(\Entities\Account $user)
    {
        $this->users[] = $user;
    }

    /**
     *
     * @param $request
     */
    public function addRequest(\Entities\ServiceRequest $request)
    {
        $this->requests[] = $request;
        $request->addService($this);
    }

    /**
     *
     * @param $request
     */
    public function addAction(\Entities\ServiceAction $action)
    {
        $this->actions[] = $action;
        $action->addService($this);
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
     * @return
     */
    public function getIsRunning()
    {
        /* we cast to integer so it outputs in the template */
        return (int) $this->is_running;
    }

    /**
     *
     * @param $is_running
     */
    public function setIsRunning($is_running)
    {
        $this->is_running = $is_running;
    }

    /**
     *
     * Enter description here ...
     *
     *
     * @return return_type
     */
    public function getMaintenanceMessage()
    {
        return $this->maintenanceMessage;
    }

    /**
     *
     * Enter description here ...
     *
     * @param unknown_type $maintenanceMessage
     *
     * @return return_type
     */
    public function setMaintenanceMessage($maintenanceMessage)
    {
        $this->maintenanceMessage = $maintenanceMessage;
    }

    /**
     *
     * @return
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     *
     * @param $users
     */
    public function setUsers($users)
    {
        $this->users = $users;
    }

    /**
     *
     * @return
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     *
     * @param $type
     */
    public function setType($type)
    {
        $this->type = $type;
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
     * @param $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
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

    /** Implement ArrayAccess */
    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    public function offsetSet($offset, $value)
    {
         throw new BadMethodCallException("Array access of class " . get_class($this) . " is read-only!");
    }

    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    public function offsetUnset($offset)
    {
         throw new BadMethodCallException("Array access of class " . get_class($this) . " is read-only!");
    }
}
