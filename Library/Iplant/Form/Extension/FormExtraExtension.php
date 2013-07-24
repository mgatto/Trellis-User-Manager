<?php

namespace Iplant\Form\Extension;

use Symfony\Component\Form\AbstractExtension;
use Iplant\Form\Extension\FormExtra\Type;
use Iplant\Form\Extension\FormExtra\Extension;

class FormExtraExtension extends AbstractExtension
{
    protected $recaptcha;
    protected $public_key;

    /* can't have a type hint for recaptcha since it could be null */
    public function __construct($recaptcha = null, $public_key = null)
    {
        $this->recaptcha = $recaptcha;
        $this->public_key = $public_key;
    }

    /**
     * loads the new form field types this extension provides
     *
     * (non-PHPdoc)
     * @see Symfony\Component\Form.AbstractExtension::loadTypes()
     */
    protected function loadTypes()
    {
        /* If I create the RecaptchaType here, I'm forcing us to instantiate the Recaptcha service even for
         * a PlainType... */
        $types = array(
            //new Type\FileSetType(),
            //new Type\PlainType(),
            //new Type\ImageType(),
        );

        if ( null !== $this->recaptcha ) {
            $types[] = new Type\RecaptchaType($this->recaptcha, $this->public_key);
        }

        return $types;
    }

    /**
     * loads an extension for types
     *
     * (non-PHPdoc)
     * @see Symfony\Component\Form.AbstractExtension::loadTypeExtensions()
     */
    protected function loadTypeExtensions() {
        return array(
            new Extension\FieldTypeExtension(),
        );
    }
}
