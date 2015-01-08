<?php

/**
 * @author:  Gabriel BONDAZ <gabriel.bondaz@idci-consulting.fr>
 * @author:  Thomas Prelot <tprelot@gmail.com>
 * @license: MIT
 */

namespace IDCI\Bundle\StepBundle\Flow;

interface FlowListenerInterface
{
    /**
     * Handle the flow.
     *
     * @param FlowInterface $flow The flow.
     */
    public function handleFlow(FlowInterface $flow);
}