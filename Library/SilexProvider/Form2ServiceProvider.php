<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SilexProvider;

use Silex\Application;
use Silex\ServiceProviderInterface;

use Symfony\Component\Form\Extension\Csrf\CsrfProvider\DefaultCsrfProvider;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension as FormValidatorExtension;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\SessionCsrfProvider;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Valid;

use Iplant\Form\Extension\FormExtraExtension;

class Form2ServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['form.secret'] = md5(__DIR__);

        $app['form.factory'] = $app->share(function () use ($app) {
            /* Prepare the FormExtraExtension's Recaptcha parameters */
            $service = null;
            $public_key = null;

            if (isset($app['recaptcha'])) {
                $service =  $app['recaptcha'];
                $public_key = $app['recaptcha.public_key'];
            }

            $extensions = array(
                new CoreExtension(),
                new CsrfExtension($app['form.csrf_provider']),
                new FormExtraExtension($service, $public_key),
            );

            if (isset($app['validator'])) {
                $extensions[] = new FormValidatorExtension($app['validator']);

                $metadata = $app['validator']->getMetadataFactory()->getClassMetadata('Symfony\Component\Form\Form');
                $metadata->addConstraint(new Callback(array(array('Symfony\Component\Form\Extension\Validator\Validator\DelegatingValidator', 'validateFormData'))));
                $metadata->addPropertyConstraint('children', new Valid());
            }

            /* good bet that $app['doctrine.orm'] must be registered before the form extension is registered! */
            /* for some reason, the doctrine extension must precede the CoreExtension,
             * probably because of the `break;` statement in FormFactory.php */
            if (isset($app['doctrine.orm'])) {
                $registry = new \SilexProvider\Doctrine2ServiceProvider\Registry(
                    $app['doctrine.orm.em']->getConnection(),
                    $app['doctrine.orm.em']
                );

                array_unshift($extensions, new \Symfony\Bridge\Doctrine\Form\DoctrineOrmExtension($registry));
            }

            return new FormFactory($extensions);
        });

        $app['form.csrf_provider'] = $app->share(function () use ($app) {
            /* @TODO test this. newly added from latest Silex code; untested in Trellis! */
            if (isset($app['session'])) {
                return new SessionCsrfProvider($app['session'], $app['form.secret']);
            }

            return new DefaultCsrfProvider($app['form.secret']);
        });

        if (isset($app['form.class_path'])) {
            $app['autoloader']->registerNamespace('Symfony\\Component\\Form', $app['form.class_path']);
        }
    }
}
