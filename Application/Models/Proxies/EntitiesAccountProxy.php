<?php

namespace Proxies;

/**
 * THIS CLASS WAS GENERATED BY THE DOCTRINE ORM. DO NOT EDIT THIS FILE.
 */
class EntitiesAccountProxy extends \Entities\Account implements \Doctrine\ORM\Proxy\Proxy
{
    private $_entityPersister;
    private $_identifier;
    public $__isInitialized__ = false;
    public function __construct($entityPersister, $identifier)
    {
        $this->_entityPersister = $entityPersister;
        $this->_identifier = $identifier;
    }
    /** @private */
    public function __load()
    {
        if (!$this->__isInitialized__ && $this->_entityPersister) {
            $this->__isInitialized__ = true;
            if ($this->_entityPersister->load($this->_identifier, $this) === null) {
                throw new \Doctrine\ORM\EntityNotFoundException();
            }
            unset($this->_entityPersister, $this->_identifier);
        }
    }
    
    
    public function getId()
    {
        $this->__load();
        return parent::getId();
    }

    public function setId($id)
    {
        $this->__load();
        return parent::setId($id);
    }

    public function getUsername()
    {
        $this->__load();
        return parent::getUsername();
    }

    public function setUsername($username)
    {
        $this->__load();
        return parent::setUsername($username);
    }

    public function getPassword()
    {
        $this->__load();
        return parent::getPassword();
    }

    public function setPassword($password, $encode = false)
    {
        $this->__load();
        return parent::setPassword($password, $encode);
    }

    public function getIpAddress()
    {
        $this->__load();
        return parent::getIpAddress();
    }

    public function setIpAddress($ipAddress = NULL)
    {
        $this->__load();
        return parent::setIpAddress($ipAddress);
    }

    public function getNotes()
    {
        $this->__load();
        return parent::getNotes();
    }

    public function setNotes($notes)
    {
        $this->__load();
        return parent::setNotes($notes);
    }

    public function getCreated()
    {
        $this->__load();
        return parent::getCreated();
    }

    public function setCreated($created)
    {
        $this->__load();
        return parent::setCreated($created);
    }

    public function getUpdated()
    {
        $this->__load();
        return parent::getUpdated();
    }

    public function setUpdated($updated)
    {
        $this->__load();
        return parent::setUpdated($updated);
    }

    public function getStatus()
    {
        $this->__load();
        return parent::getStatus();
    }

    public function setStatus($status)
    {
        $this->__load();
        return parent::setStatus($status);
    }

    public function setPerson(\Entities\Person $person = NULL)
    {
        $this->__load();
        return parent::setPerson($person);
    }

    public function getPerson()
    {
        $this->__load();
        return parent::getPerson();
    }

    public function addService(\Entities\Service $service)
    {
        $this->__load();
        return parent::addService($service);
    }

    public function getServices()
    {
        $this->__load();
        return parent::getServices();
    }

    public function addRequest(\Entities\ServiceRequest $request)
    {
        $this->__load();
        return parent::addRequest($request);
    }

    public function setRequests($requests)
    {
        $this->__load();
        return parent::setRequests($requests);
    }

    public function getRequests()
    {
        $this->__load();
        return parent::getRequests();
    }

    public function addClient(\Entities\ApiClient $client)
    {
        $this->__load();
        return parent::addClient($client);
    }

    public function setClients($clients)
    {
        $this->__load();
        return parent::setClients($clients);
    }

    public function getClients()
    {
        $this->__load();
        return parent::getClients();
    }

    public function setServices($services)
    {
        $this->__load();
        return parent::setServices($services);
    }

    public function addToken(\Entities\Token $token)
    {
        $this->__load();
        return parent::addToken($token);
    }

    public function getTokens()
    {
        $this->__load();
        return parent::getTokens();
    }

    public function setTokens($tokens)
    {
        $this->__load();
        return parent::setTokens($tokens);
    }

    public function offsetExists($offset)
    {
        $this->__load();
        return parent::offsetExists($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->__load();
        return parent::offsetSet($offset, $value);
    }

    public function offsetGet($offset)
    {
        $this->__load();
        return parent::offsetGet($offset);
    }

    public function offsetUnset($offset)
    {
        $this->__load();
        return parent::offsetUnset($offset);
    }

    public function getIsValidated()
    {
        $this->__load();
        return parent::getIsValidated();
    }

    public function setIsValidated($is_validated)
    {
        $this->__load();
        return parent::setIsValidated($is_validated);
    }

    public function onPreUpdate()
    {
        $this->__load();
        return parent::onPreUpdate();
    }

    public function encodePassword()
    {
        $this->__load();
        return parent::encodePassword();
    }

    public function createTempPassword($seed = NULL)
    {
        $this->__load();
        return parent::createTempPassword($seed);
    }

    public function isUsernameProhibited()
    {
        $this->__load();
        return parent::isUsernameProhibited();
    }


    public function __sleep()
    {
        return array('__isInitialized__', 'id', 'username', 'password', 'ip_address', 'is_validated', 'notes', 'created', 'updated', 'status', 'person', 'services', 'clients', 'requests', 'tokens');
    }

    public function __clone()
    {
        if (!$this->__isInitialized__ && $this->_entityPersister) {
            $this->__isInitialized__ = true;
            $class = $this->_entityPersister->getClassMetadata();
            $original = $this->_entityPersister->load($this->_identifier);
            if ($original === null) {
                throw new \Doctrine\ORM\EntityNotFoundException();
            }
            foreach ($class->reflFields AS $field => $reflProperty) {
                $reflProperty->setValue($this, $reflProperty->getValue($original));
            }
            unset($this->_entityPersister, $this->_identifier);
        }
        
    }
}