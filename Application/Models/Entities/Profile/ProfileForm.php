<?php
namespace Entities\Profile;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Doctrine\ORM\EntityRepository;

class ProfileForm extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->add('institution', new \Entities\Institution\InstitutionForm());

        $builder->add('department');

        $builder->add('position', 'entity', array(
            'class' => 'Entities\Position',
            'property' => 'name',
            'required' => true,
            'multiple' => false,
        ));

        $builder->add('research_area', 'entity', array(
            'class' => 'Entities\ResearchArea',
            'property' => 'name',
            /* we appended new areas post-db build, thus need to alphabetize them programmatically  */
            'query_builder' => function (EntityRepository $repository) {
                return $repository
                    ->createQueryBuilder('ra')
                    ->orderBy('ra.name', 'ASC');
            },
            'required' => false,
            'multiple' => false,
        ));

        $builder->add('participate_in_survey', 'choice', array(
            'choices'   => array('1' => 'Yes', '0' => 'No'),
            'required'  => false,
            /* the following combo of options and their values = radio buttons */
            'expanded' => true,
            'multiple' => false,
        ));

        $builder->add('how_heard_about', 'choice', array(
            'choices'   => array(
                'friend' => 'Friend',
                'student' => 'Student',
                'instructor' => 'Instructor',
                'colleague' => 'Colleague',
                'workshop' => 'Workshop',
                'convention' => 'Convention',
                'direct email' => 'Direct Email',
                'search engine' => 'Search Engine',
                'internet' => 'Internet',
                'other' => 'Other',
            ),
            'required'  => false,
            /* the following combo of options and their values = select box */
            'expanded' => false,
            'multiple' => false,
        ));
    }

    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class' => 'Entities\Profile',
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
        return 'profile';
    }
}
