<?php

namespace SilexProvider\AuthServiceProvider;

class Storage implements \Zend_Auth_Storage_Interface {

    /**
     *
     * @var
     */
    protected $session;

    /**
     *
     * @var
     */
    protected $namespace;

    /**
     *
     * @var
     */
    protected $member = 'storage';

    /**
     *
     *
     * @param \Symfony\HttpFoundation\Session $session
     * @param string $namespace
     *
     * @return return_type
     */
    public function __construct($session, $namespace = 'auth') {
        $this->namespace = $namespace;
        $this->session = $session;//->set($namespace, array());
    }

    /**
     * (non-PHPdoc)
     * @see Zend_Auth_Storage_Interface::isEmpty()
     */
    public function isEmpty() {
        /* must return the inverse of has()'s boolean result */
        return ! $this->session->has($this->namespace);
        //throw new \Zend_Auth_Storage_Exception();
    }

    /**
     * (non-PHPdoc)
     * @see Zend_Auth_Storage_Interface::read()
     */
    public function read() {
        return $this->session->get($this->namespace);
    }

    /**
     * (non-PHPdoc)
     * @see Zend_Auth_Storage_Interface::write()
     */
    public function write($contents) {
        $this->session->set($this->namespace, $contents);
    }

    /**
     * (non-PHPdoc)
     * @see Zend_Auth_Storage_Interface::clear()
     */
    public function clear() {
        $this->session->remove($this->namespace);
    }
}
