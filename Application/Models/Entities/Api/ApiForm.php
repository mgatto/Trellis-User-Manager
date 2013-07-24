<?php
namespace Entities\Api;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;

class ApiForm extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->add('id', 'hidden');
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Form.AbstractType::getDefaultOptions()
     */
    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class'      => 'Entities\Api',
            'csrf_protection' => false,
        );
    }

    /**
     * Most recent Symfony2 commits removed name guessing from AbstractType.php
     *
     * @param void
     *
     * @return string
     */
    public function getName()
    {
        return 'api';
    }
}
