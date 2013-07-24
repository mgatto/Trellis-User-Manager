<?php
namespace Entities\Account;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;

class AccountForm extends AbstractType
{
    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Form.AbstractType::buildForm()
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder->add('username', 'text', array(
            'max_length' => 64,
        ));

        $builder->add('password', 'repeated', array(
            'type'=>'password',
            'first_name' => 'password',
            'second_name' => 'confirm_password',
            'invalid_message' => 'The password and confirmation must match',
            'options' => array(
                //'label' => 'Password',
                'max_length' => 64,
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
            'data_class' => 'Entities\Account',
            /*'validation_groups' => array('registration','reset'),*/
            /* groups can be set to a null array to disable this form's
             * validation when its a child form, for example, rather than
             * creating a bunch of groups */
            //'validation_groups' => array()
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
        return 'account';
    }
}
