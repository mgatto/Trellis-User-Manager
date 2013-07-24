<?php

namespace Proxies;

/**
 * THIS CLASS WAS GENERATED BY THE DOCTRINE ORM. DO NOT EDIT THIS FILE.
 */
class EntitiesFundingAgencyProxy extends \Entities\FundingAgency implements \Doctrine\ORM\Proxy\Proxy
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

    public function getName()
    {
        $this->__load();
        return parent::getName();
    }

    public function setName($name)
    {
        $this->__load();
        return parent::setName($name);
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

    public function setInstitutions($institutions = NULL)
    {
        $this->__load();
        return parent::setInstitutions($institutions);
    }

    public function getInstitutions()
    {
        $this->__load();
        return parent::getInstitutions();
    }

    public function addInstitution(\Entities\Institution $institution)
    {
        $this->__load();
        return parent::addInstitution($institution);
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


    public function __sleep()
    {
        return array('__isInitialized__', 'id', 'name', 'notes', 'institutions');
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