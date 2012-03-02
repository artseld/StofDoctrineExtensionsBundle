<?php

namespace Stof\DoctrineExtensionsBundle\Form\Type;

use Symfony\Component\Form\FormBuilder;
use Symfony\Bridge\Doctrine\RegistryInterface;
//use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;
use Symfony\Bridge\Doctrine\Form\EventListener\MergeCollectionListener;
use Symfony\Bridge\Doctrine\Form\DataTransformer\EntitiesToArrayTransformer;
use Symfony\Bridge\Doctrine\Form\DataTransformer\EntityToIdTransformer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use Stof\DoctrineExtensionsBundle\Form\ChoiceList\GedmoTreeChoiceList;

class GedmoTreeType extends EntityType
{
    public function buildForm(FormBuilder $builder, array $options)
    {
        if (!$options['expanded']) {
            throw new \RuntimeException("Gedmo tree form type should be expanded", 500);
        }

        parent::buildForm($builder, $options);
    }

    public function getDefaultOptions(array $options)
    {
        $defaultOptions = array(
            'em'                => null,
            'class'             => null,
            'property'          => null,
            'query_builder'     => null,
            'choices'           => null,
            'expanded'          => true,
            'multiple'          => true,
            'roots'             => false,
        );

        $options = array_replace($defaultOptions, $options);

        if (!isset($options['choice_list'])) {
            $defaultOptions['choice_list'] = new GedmoTreeChoiceList(
                $this->registry->getEntityManager($options['em']),
                $options['class'],
                $options['property'],
                $options['query_builder'],
                $options['choices'],
                $options['roots']
            );
        }

        return $defaultOptions;
    }

    public function getParent(array $options)
    {
        return 'choice';
    }

    public function getName()
    {
        return 'gedmo_tree';
    }
}
