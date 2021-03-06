<?php
/*
 * Copyright (c) 2014 Eltrino LLC (http://eltrino.com)
 *
 * Licensed under the Open Software License (OSL 3.0).
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://opensource.org/licenses/osl-3.0.php
 *
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@eltrino.com so we can send you a copy immediately.
 */

namespace Diamante\DeskBundle\Form\Type;

use Diamante\DeskBundle\Form\DataTransformer\UserTransformer;
use Doctrine\ORM\EntityManager;
use Oro\Bundle\FormBundle\Autocomplete\ConverterInterface;
use Oro\Bundle\FormBundle\Autocomplete\SearchRegistry;
use Oro\Bundle\ImapBundle\Connector\Exception\InvalidConfigurationException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class DiamanteJquerySelect2HiddenType extends AbstractType
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var SearchRegistry
     */
    protected $searchRegistry;

    public function __construct(SearchRegistry $registry)
    {
        $this->searchRegistry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $defaultConfig = array(
            'placeholder'        => 'oro.form.choose_value',
            'allowClear'         => true,
            'minimumInputLength' => 1,
        );

        $searchRegistry = $this->searchRegistry;
        $formType = $this;

        $resolver
            ->setDefaults(
                array(
                    'empty_value'        => '',
                    'empty_data'         => null,
                    'data_class'         => null,
                    'entity_class'       => null,
                    'configs'            => $defaultConfig,
                    'converter'          => null,
                    'autocomplete_alias' => null,
                    'excluded'           => null,
                    'random_id'          => true,
                )
            );

        $this->setConverterNormalizer($resolver);
        $this->setConfigsNormalizer($resolver, $defaultConfig);

        $resolver
            ->setNormalizers(
                array(
                    'entity_class' => function (Options $options, $value) use ($searchRegistry) {
                        if (!$value && !empty($options['autocomplete_alias'])) {
                            $searchHandler = $searchRegistry->getSearchHandler($options['autocomplete_alias']);
                            $value = $searchHandler->getEntityName();
                        }

                        if (!$value) {
                            throw new InvalidConfigurationException('The option "entity_class" must be set.');
                        }
                        return $value;
                    },
                    'transformer' => function (Options $options, $value) use ($formType) {
                        if (!$value) {
                            $value = $formType->createDefaultTransformer();
                        } elseif (!$value instanceof DataTransformerInterface) {
                            throw new TransformationFailedException(
                                sprintf(
                                    'The option "transformer" must be an instance of "%s".',
                                    'Symfony\Component\Form\DataTransformerInterface'
                                )
                            );
                        }
                        return $value;
                    }
                )
            );
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    protected function setConverterNormalizer(OptionsResolverInterface $resolver)
    {
        $searchRegistry = $this->searchRegistry;
        $resolver->setNormalizers(
            array(
                'converter' => function (Options $options, $value) use ($searchRegistry) {
                    if (!$value && !empty($options['autocomplete_alias'])) {
                        $value = $searchRegistry->getSearchHandler($options['autocomplete_alias']);
                    } elseif (!$value) {
                        throw new InvalidConfigurationException('The option "converter" must be set.');
                    }

                    if (!$value instanceof ConverterInterface) {
                        throw new UnexpectedTypeException(
                            $value,
                            'Oro\Bundle\FormBundle\Autocomplete\ConverterInterface'
                        );
                    }
                    return $value;
                }
            )
        );
    }

    /**
     * @param OptionsResolverInterface $resolver
     * @param array $defaultConfig
     */
    protected function setConfigsNormalizer(OptionsResolverInterface $resolver, array $defaultConfig)
    {
        $searchRegistry = $this->searchRegistry;
        $resolver->setNormalizers(
            array(
                'configs' => function (Options $options, $configs) use ($searchRegistry, $defaultConfig) {
                    $result = array_replace_recursive($defaultConfig, $configs);

                    if (!empty($options['autocomplete_alias'])) {
                        $result['autocomplete_alias'] = $options['autocomplete_alias'];
                        if (empty($result['properties'])) {
                            $searchHandler = $searchRegistry->getSearchHandler($options['autocomplete_alias']);
                            $result['properties'] = $searchHandler->getProperties();
                        }
                        if (empty($result['route_name'])) {
                            $result['route_name'] = 'oro_form_autocomplete_search';
                        }
                        if (empty($result['extra_config'])) {
                            $result['extra_config'] = 'autocomplete';
                        }
                    }

                    if (!array_key_exists('route_parameters', $result)) {
                        $result['route_parameters'] = array();
                    }

                    if (empty($result['route_name'])) {
                        throw new InvalidConfigurationException(
                            'Option "configs.route_name" must be set.'
                        );
                    }

                    return $result;
                }
            )
        );
    }

    /**
     * @return UserTransformer
     */
    public function createDefaultTransformer()
    {
        return $value = new UserTransformer();
    }

    /**
     * Set data-title attribute to element to show selected value
     *
     * @param FormView $view
     * @param FormInterface $form
     * @param array $options
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $vars = array(
            'configs' => $options['configs'],
            'excluded' => (array)$options['excluded']
        );

        if ($form->getData()) {
            $result = array();
            /** @var ConverterInterface $converter */
            $converter = $options['converter'];
            if (isset($options['configs']['multiple']) && $options['configs']['multiple']) {
                foreach ($form->getData() as $item) {
                    $result[] = $converter->convertItem($item);
                }
            } else {
                $result[] = $converter->convertItem($form->getData());
            }

            $vars['attr'] = array(
                'data-selected-data' => json_encode($result)
            );
        }

        $view->vars = array_replace_recursive($view->vars, $vars);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'genemu_jqueryselect2_hidden';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'diamante_jqueryselect2_hidden';
    }
} 