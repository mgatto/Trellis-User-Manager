<?php
namespace Entities\ApiClient\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;

class TrellisUserApiClientForm extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->add('id', 'hidden');

        $builder->add('name', 'text', array(
            'required' => true,
            'label' => "What do you call your API Client?",
        ));

        $builder->add('url', 'text', array(
            'required' => true,
            'label' => "Website about your client (homepage, project site, documentation, etc.)",
        ));

        $builder->add('ip_address', 'text', array(
            'required' => true,
            'label' => "At what IP Address(es) does your API client reside?",
            'attr' => array(
                'max_length' => 83,
            ),
        ));

        $builder->add('description', 'textarea', array(
            'required' => true,
            'label' => "Describe your API Client",
        ));

        $builder->add('how_will_use', 'textarea', array(
            'required' => true,
            'label' => "How will you use the data which your API client consumes?",
        ));

        $builder->add('api', new \Entities\Api\ApiForm());
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Form.AbstractType::getDefaultOptions()
     */
    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class'      => 'Entities\ApiClient\TrellisUserApiClient',
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
    public function getName()
    {
        return 'trellis_api_client';
    }
}
