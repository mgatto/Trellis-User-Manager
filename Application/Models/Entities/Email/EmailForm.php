<?php
namespace Entities\Email;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;

class EmailForm extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        /* Don't make this of type 'email' since Symfony will throw an error
         * if a field type is the same name as the form gotten from $this->getName()
         * below. */
        $builder->add('email', 'text'); /*, 'email', array(
            'required' => true,
        )*/
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Form.AbstractType::getDefaultOptions()
     */
    public function getDefaultOptions(array $options)
    {
        return array(
            'data_class' => 'Entities\Email',
        );
    }

    /**
     * Recent Symfony2 Beta commit removed name guessing from AbstractType.php
     *
     * We must define this ourselves in each form. Careful not to name it the
     * same name as any type of form or type of input used in your codebase or
     * in Symfony.
     *
     * @param void
     *
     * @return string
     */
    public function getName() {
        return 'email';
    }
}
