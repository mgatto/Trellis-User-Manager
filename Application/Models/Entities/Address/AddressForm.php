<?php
namespace Entities\Address;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;

class AddressForm extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->add('country', 'country', array(
            'multiple' => false,
            'expanded' => false,
            'required' => true,
            'preferred_choices' => array('US','CN','IN','GB'),
        ));

        $builder->add('city', 'text', array(
            'required' => true,
            'max_length' => 96,
        ));

        $builder->add('state', 'text', array(
            'required' => true,
        ));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class' => 'Entities\Address',
        );
    }

    /**
     * Most recent Symfony2 commits removed name guessing from AbstractType.php
     *
     */
    public function getName() {
        return 'address';
    }
}
