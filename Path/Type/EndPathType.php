<?php

/**
 * @author:  Gabriel BONDAZ <gabriel.bondaz@idci-consulting.fr>
 * @license: MIT
 */
 
namespace IDCI\Bundle\StepBundle\Path\Type;

use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use IDCI\Bundle\StepBundle\Path\PathInterface;
use IDCI\Bundle\StepBundle\Map\MapInterface;

class EndPathType extends AbstractPathType
{
    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver
            ->setRequired(array('source', 'label', 'storageProvider'))
            ->setDefaults(array('label' => 'end'))
            ->setAllowedTypes(array(
                'source'          => 'string',
                'label'           => 'string',
                'storageProvider' => 'string',
            ))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function buildPath(PathInterface $path, MapInterface $map, array $options = array())
    {
        return $path->setSource($map->getStep($options['source']));
    }
}