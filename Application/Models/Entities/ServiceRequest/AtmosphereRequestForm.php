<?php
namespace Entities\ServiceRequest;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;

class AtmosphereRequestForm extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        /* The id is currently rendered by form_rest and not explicitly in
         * the Twig template: user/form.phtml */
        $builder->add('id', 'hidden');

        /* Seems to be only required for Atmosphere, but probably useful for all */
        $builder->add('how_will_use', 'textarea', array(
            'required' => true,
            'label' => 'Describe how you plan to use Atmosphere',
        ));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class' => 'Entities\ServiceRequest\AtmosphereRequest',
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
        return 'atmosphere_request';
    }
}




