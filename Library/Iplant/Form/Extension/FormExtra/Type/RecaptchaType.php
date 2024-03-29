<?php

namespace Iplant\Form\Extension\FormExtra\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;

use Iplant\Service\Recaptcha;
use Iplant\Form\Extension\FormExtra\DataTransformer\RecaptchaTransformer;

/**
 * A ReCaptcha type for use with Google ReCatpcha services. It embeds two fields that are used
 * for manual validation and show of the widget.
 *
 * The DataTransformer takes the entered request information and validates them agains the
 * Google Recaptcha API.
 *
 * example:
 *     $builder->add('recaptcha', 'recaptcha', array(
 *         'private_key' => 'private_key_here_required',
 *         'public_key' => 'public_key_here_required',
 *     ))
 *
 *
 * @author Henrik Bjornskov <henrik@bjrnskov.dk>
 */
class RecaptchaType extends AbstractType
{
    /**
     * @var Recaptcha
     */
    protected $recaptcha;

    /**
     * @var string
     */
    protected $publicKey;

    /**
     * @var bool
     */
    protected $use_ssl;

    /**
     * @param Recaptcha $recaptcha
     * @param string $publicKey
     */
    public function __construct(Recaptcha $recaptcha, $publicKey, $use_ssl = false)
    {
        $this->recaptcha = $recaptcha;
        $this->publicKey = $publicKey;
    }

    /**
     * Configures the Type
     *
     * @param FormBuilder $builder
     * @param array       $options
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        $builder
            ->add('recaptcha_challenge_field', 'text')
            ->add('recaptcha_response_field', 'hidden', array(
                'data' => 'manual_challenge',
            ))
        ;

        $builder->prependClientTransformer(new RecaptchaTransformer($this->recaptcha));
        $builder->setAttribute('widget_options', $options['widget_options']);
        $builder->setAttribute('use_ssl', $options['use_ssl']);
    }

    /**
     * Sets attributes for use with the renderer
     *
     * @param FormView $view
     * @param FormInterface $form
     */
    public function buildView(FormView $view, FormInterface $form)
    {
        $view->set('public_key', $this->publicKey);
        $view->set('widget_options', $form->getAttribute('widget_options'));
        $view->set('use_ssl', $form->getAttribute('use_ssl'));
    }

    /**
     * Options for this type
     *
     * @param  array $options
     * @return array
     */
    public function getDefaultOptions(array $options)
    {
        return array(
            'required'        => true,
            'property_path'   => false,
            'widget_options'  => array(),
            'error_bubbling'  => false,
            /* we will use SSL universally in Trellis, now */
            'use_ssl'		  => true,
        );
    }

    /**
     * Because this have property_path = null and it shouldnt be written this parent
     * is a field.
     *
     * @return string
     */
    public function getParent(array $options)
    {
        return 'form';
    }

    /**
     * Used to identify the rendering block
     *
     * @return string
     */
    public function getName()
    {
        return 'recaptcha';
    }
}
