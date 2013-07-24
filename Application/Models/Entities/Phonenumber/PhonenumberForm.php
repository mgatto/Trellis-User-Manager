<?php
namespace Entities\Phonenumber;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;

class PhonenumberForm extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->add('number', 'text', array(
            'required' => false,
        ));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class' => 'Entities\Phonenumber',
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
        return 'phonenumber';
    }
}
