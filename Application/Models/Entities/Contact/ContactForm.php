<?php
namespace Entities\Contact;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;

class ContactForm extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->add('email', 'text', array(
            'required' => true,
            'property_path' => 'email',
        ));

        $builder->add('body', 'textarea', array(
            'required' => true,
        ));

        $builder->add('recaptcha', 'recaptcha', array(
            'widget_options' => array(
            'theme' => 'white',
            'use_ssl' => true,
            ),
        ));
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Form.AbstractType::getDefaultOptions()
     */
    public function getDefaultOptions(array $options)
    {
        return array(
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
        );
    }

    /**
     * Most recent Symfony2 commits removed name guessing from AbstractType.php
     *
     * @param void
     *
     * @return string
     */
    public function getName() {
        return 'contact';
    }
}
