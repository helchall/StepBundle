<?php

/**
 * @author:  Gabriel BONDAZ <gabriel.bondaz@idci-consulting.fr>
 * @license: MIT
 */

namespace IDCI\Bundle\StepBundle\Step;

interface StepBuilderInterface
{
    /**
     * Returns built step.
     *
     * @param string        $name       The name.
     * @param string        $typeAlias  The type alias.
     * @param array         $options    The options.
     *
     * @return StepInterface The step.
     */
    public function build($name, $typeAlias, array $options = array());
}