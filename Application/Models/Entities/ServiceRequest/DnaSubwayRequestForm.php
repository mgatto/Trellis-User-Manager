<?php
namespace Entities\ServiceRequest;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;

class DnaSubwayRequestForm extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        /* The id is currently rendered by form_rest and not explicitly in
         * the Twig template: user/form.phtml */
        $builder->add('id', 'hidden');

         $builder->add('how_will_use', 'choice', array(
            'label' => 'How will you use DNA Subway?',
            'choices'   => array(
                'Class Assignment' => 'Class Assignment',
                'Research Project' => 'Research Project',
                'Other' => 'Other',
            ),
            'required'  => true,
            'expanded' => true,
            'multiple' => true,
        ));

        /* School Information */
        $builder->add('school_name', 'text', array(
            'label' => 'School Name:',
            'required' => false,
        ));
        $builder->add('school_type', 'choice', array(
            'label' => 'School Type:',
            'choices'   => array(
                'Graduate School' => 'Graduate School',
                '4-Year College/University' => '4-Year College/University',
                '2-Year College' => '2-Year College',
                'College Preparatory' => 'College Preparatory',
                'High School' => 'High School',
                'Other' => 'Other',
            ),
            'required'  => false,
            'expanded' => false,
            'multiple' => false,
        ));
        $builder->add('school_surrounding_area','choice', array(
            'label' => "School's Surrounding Area is:",
            'choices'   => array(
                'Urban' => 'Urban',
                'Suburban' => 'Suburban',
                'Rural' => 'Rural',
            ),
            'required'  => false,
            'expanded' => false,
            'multiple' => false,
        ));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class' => 'Entities\ServiceRequest\DnaSubwayRequest',
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
        return 'dnasubway_request';
    }
}
