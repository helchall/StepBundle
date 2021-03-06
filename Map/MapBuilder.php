<?php

/**
 * @author:  Gabriel BONDAZ <gabriel.bondaz@idci-consulting.fr>
 * @license: MIT
 */

namespace IDCI\Bundle\StepBundle\Map;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use IDCI\Bundle\StepBundle\Flow\FlowRecorderInterface;
use IDCI\Bundle\StepBundle\Flow\FlowData;
use IDCI\Bundle\StepBundle\Map\MapInterface;
use IDCI\Bundle\StepBundle\Step\StepBuilderInterface;
use IDCI\Bundle\StepBundle\Path\PathBuilderInterface;

class MapBuilder implements MapBuilderInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $data;

    /**
     * @var array
     */
    private $options;

    /**
     * @var array
     */
    private $steps;

    /**
     * @var array
     */
    private $paths;

    /**
     * @var FlowRecorderInterface
     */
    private $flowRecorder;

    /**
     * @var StepBuilderInterface
     */
    private $stepBuilder;

    /**
     * @var PathBuilderInterface
     */
    private $pathBuilder;

    /**
     * @var \Twig_Environment
     */
    private $merger;

    /**
     * @var SecurityContextInterface
     */
    private $securityContext;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * Creates a new map builder.
     *
     * @param string                   $name            The map name.
     * @param array                    $data            The map data.
     * @param array                    $options         The map options.
     * @param FlowRecorderInterface    $flowRecorder    The flow recorder.
     * @param StepBuilderInterface     $stepBuilder     The step builder.
     * @param PathBuilderInterface     $pathBuilder     The path builder.
     * @param Twig_Environment         $merger          The twig merger.
     * @param SecurityContextInterface $securityContext The security context.
     * @param SessionInterface         $session         The session.
     */
    public function __construct(
        $name    = null,
        $data    = array(),
        $options = array(),
        FlowRecorderInterface    $flowRecorder,
        StepBuilderInterface     $stepBuilder,
        PathBuilderInterface     $pathBuilder,
        \Twig_Environment        $merger,
        SecurityContextInterface $securityContext,
        SessionInterface         $session
    )
    {
        $this->name            = $name;
        $this->data            = $data;
        $this->options         = self::resolveOptions($options);
        $this->flowRecorder    = $flowRecorder;
        $this->stepBuilder     = $stepBuilder;
        $this->pathBuilder     = $pathBuilder;
        $this->merger          = $merger;
        $this->securityContext = $securityContext;
        $this->session         = $session;
        $this->steps           = array();
        $this->paths           = array();
    }

    /**
     * Resolve options
     *
     * @param array $options The options to resolve.
     *
     * @return array The resolved options.
     */
    public static function resolveOptions(array $options = array())
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setDefaults(array(
                'browsing'            => 'linear',
                'first_step_name'     => null,
                'final_destination'   => null,
                'display_step_in_url' => false,
            ))
            ->setAllowedTypes(array(
                'display_step_in_url' => array('bool'),
            ))
            ->setAllowedValues(array(
                'browsing' => array('linear', 'free'),
            ))
        ;

        return $resolver->resolve($options);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions()
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function hasOption($name)
    {
        return array_key_exists($name, $this->options);
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($name, $default = null)
    {
        return $this->hasOption($name) ? $this->options[$name] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function addStep($name, $type, array $options = array())
    {
        $this->steps[$name] = array(
            'type'      => $type,
            'options'   => $options
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addPath($type, array $options = array())
    {
        $this->paths[] = array(
            'type'      => $type,
            'options'   => $options
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMap(Request $request)
    {
        return $this->build($request);
    }

    /**
     * Build the map.
     *
     * @param Request $request The HTTP request.
     *
     * @return MapInterface the built map.
     */
    private function build(Request $request)
    {
        // TODO: Use a MapConfig as argument instead of an array.
        $map = new Map(array(
            'name'      => $this->name,
            'footprint' => $this->generateFootprint(),
            'data'      => $this->merge($this->data),
            'options'   => $this->options
        ));

        // Build steps before paths !
        $this->buildSteps($map, $request);
        $this->buildPaths($map, $request);

        return $map;
    }

    /**
     * Build steps into the map.
     *
     * @param MapInterface $map     The building map.
     * @param Request      $request The HTTP request.
     */
    private function buildSteps(MapInterface $map, Request $request)
    {
        $vars = array();
        $flow = $this->flowRecorder->getFlow($map, $request);

        if (null !== $flow) {
            $vars['flow_data'] = $flow->getData();
        }

        foreach ($this->steps as $name => $parameters) {
            $stepOptions = $this->merge($parameters['options'], $vars);

            $step = $this->stepBuilder->build(
                $name,
                $parameters['type'],
                $stepOptions
            );

            if (null !== $step) {
                $map->addStep($name, $step);
            }

            if (null === $map->getFirstStepName() || $step->isFirst()) {
                $map->setFirstStepName($name);
            }
        }
    }

    /**
     * Build paths into the map.
     *
     * @param MapInterface $map     The building map.
     * @param Request      $request The HTTP request.
     */
    private function buildPaths(MapInterface $map, Request $request)
    {
        foreach ($this->paths as $parameters) {
            $path = $this->pathBuilder->build(
                $parameters['type'],
                $parameters['options'],
                $map->getSteps()
            );

            if (null !== $path) {
                $map->addPath(
                    $path->getSource()->getName(),
                    $path
                );
            }
        }
    }

    /**
     * Generate a map unique footprint based on its name, steps and paths.
     *
     * @return string
     */
    private function generateFootprint()
    {
        return md5($this->name.json_encode($this->steps).json_encode($this->paths));
    }

    /**
     * Merge options with the SecurityContext (user)
     * and the session (session).
     *
     * @param array $options The options.
     * @param array $vars    The merging vars.
     *
     * @return array
     */
    private function merge(array $options = array(), array $vars = array())
    {
        $vars['session'] = $this->session->all();
        $vars['user']    = null !== $this->securityContext->getToken() ?
            $this->securityContext->getToken()->getUser() :
            null
        ;

        return $this->mergeValue($options, $vars, false);
    }

    /**
     * Merge a value.
     *
     * @param mixed $value The value.
     * @param array $vars  The merging vars.
     * @param array $try   Whether or not to just try merging.
     *
     * @return mixed The merged value.
     */
    private function mergeValue($value, array $vars = array(), $try = true)
    {
        // Handle array case.
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                // Do not merge if ending with '|raw'.
                if (substr($k, -4) == '|raw') {
                    $value[substr($k, 0, -4)] = $v;
                    unset($value[$k]);
                // Do not merge events parameters.
                } elseif ($k !== 'events') {
                   $value[$k] = $this->mergeValue($v, $vars, $try);
                }
            }
        // Handle object case.
        } elseif (is_object($value)) {
            $class = new \ReflectionClass($value);
            $properties = $class->getProperties();

            foreach ($properties as $property) {
                $property->setAccessible(true);

                $property->setValue(
                    $value,
                    $this->mergeValue(
                        $property->getValue($value),
                        $vars
                    )
                );
            }
        // Handle string case.
        } elseif (is_string($value)) {
            try {
                $value = $this->merger->render($value, $vars);
            } catch (\Exception $e) {
                if (!$try) {
                    throw $e;
                }
            }
        }

        return $value;
    }
}