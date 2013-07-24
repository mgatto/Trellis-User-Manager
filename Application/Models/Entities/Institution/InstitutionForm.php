<?php
namespace Entities\Institution;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;

class InstitutionForm extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->add('name');

        $builder->add('funding_agencies', 'entity', array(
            'class' => 'Entities\FundingAgency',
            'property' => 'name',
            'expanded' => false,
            'multiple' => true,
            'required' => false,
        ));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class' => 'Entities\Institution',
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
        return 'institution';
    }
}
