<?php
namespace Entities\Person;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;

class PersonForm extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        /* The id is currently rendered by form_rest and not explicitly in
         * the Twig template: user/form.phtml */
        $builder->add('id', 'hidden');
        $builder->add('firstname');
        $builder->add('lastname');
        $builder->add('gender', 'choice', array(
            'label' => 'Gender (optional)',
            'choices'   => array('male' => 'Male', 'female' => 'Female', 'declined' => 'Decline to Answer'),
            'required'  => true,
            'expanded' => true,
            'multiple' => false,
        ));
        $builder->add('ethnicity', 'entity', array(
            'label' => "Etnicity (Optional)",
            'class' => 'Entities\Ethnicity',
            'property' => 'name',
            'expanded' => false,
            'multiple' => false,
            'required' => true,
            'preferred_choices' => array('Decline to Provide'),
        ));
        $builder->add('citizenship', 'country', array(
            'label' => 'Citizenship',
            'multiple' => false,
            'expanded' => false,
            'required' => true,
            'preferred_choices' => array('ZZ','US','CN','IN','GB'),
        ));

        $builder->add('account', new \Entities\Account\AccountForm());
        $builder->add('emails', 'collection', array(
            'type' => new \Entities\Email\EmailForm(),
        ));
        $builder->add('phonenumbers', 'collection', array(
            'type' => new \Entities\Phonenumber\PhonenumberForm(),
        ));
        $builder->add('faxnumbers', 'collection', array(
            'type' => new \Entities\Faxnumber\FaxnumberForm(),
        ));
        $builder->add('address', new \Entities\Address\AddressForm());
        $builder->add('profile', new \Entities\Profile\ProfileForm());
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Form.AbstractType::getDefaultOptions()
     */
    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class'      => 'Entities\Person',
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
        return 'person';
    }
}
