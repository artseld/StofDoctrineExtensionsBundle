<?php

namespace Stof\DoctrineExtensionsBundle\Form\ChoiceList;

use Symfony\Component\Form\Util\PropertyPath;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
//use Symfony\Component\Form\Extension\Core\ChoiceList\ArrayChoiceList;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\NoResultException;

use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;

class GedmoTreeChoiceList extends EntityChoiceList
{
    // Additional `gedmo_tree` properties (used for label parts setting)
    protected $gedmoTreeProperties = array(
        'id'        => array('source' => null, 'public' => true),
        'parent'    => array('source' => null, 'public' => true),
//        'children'  => array('source' => null, 'public' => true),
        'roots'     => array('show' => false, 'ids' => array()),
    );

    /**
     * Constructor.
     *
     * @param EntityManager         $em           An EntityManager instance
     * @param string                $class        The class name
     * @param string                $property     The property name
     * @param QueryBuilder|\Closure $queryBuilder An optional query builder
     * @param array|\Closure        $choices      An array of choices or a function returning an array
     * @param boolean               $roots        Flag to show roots
     */
    public function __construct(EntityManager $em, $class, $property = null, $queryBuilder = null, $choices = null, $roots = false)
    {
        $this->checkEntity($em, $class);

        $this->gedmoTreeProperties['roots']['show'] = (bool) $roots;
        if (!$this->gedmoTreeProperties['roots']['show']) {
            $rootNodes = $em->getRepository($class)->getRootNodes();
            foreach ($rootNodes as $rootNode) {
                $this->gedmoTreeProperties['roots']['ids'][] = $this->gedmoTreeProperties['id']['public'] ?
                    $rootNode->{$this->gedmoTreeProperties['id']['source']} :
                    $rootNode->{$this->gedmoTreeProperties['id']['source']}();
            }
            unset($rootsNodes);
        }

        parent::__construct($em, $class, $property, $queryBuilder, $choices);
    }

    /**
     * Validate entity (read and check annotations, properties, etc.)
     * @param \Doctrine\ORM\EntityManager $em
     * @param $class
     * @throws \InvalidArgumentException
     */
    protected function checkEntity(EntityManager $em, $class)
    {
        $reader = new \Doctrine\Common\Annotations\AnnotationReader();
        $metadata = $em->getClassMetadata($class);

        // check class
        $annotations = $reader->getClassAnnotations( $metadata->getReflectionClass() );
        $error = true;
        foreach ($annotations as $annotation) {
            if ($annotation instanceof \Gedmo\Mapping\Annotation\Tree) $error = false;
        }
        if ($error) {
            throw new \InvalidArgumentException('Trying to use `gedmo_tree` field type with inappropriate entity');
        }

        // check properties
        $errorId = true;
        $errorParent = true;
        foreach ($metadata->getReflectionProperties() as $property) {
            $annotations = $reader->getPropertyAnnotations( $property );
            foreach ($annotations as $annotation) {
                $field = false;
                if ($annotation instanceof \Doctrine\ORM\Mapping\Id) {
                    $this->gedmoTreeProperties['id']['source'] = $property->name;
                    $errorId = false;
                    $field = 'id';
                }
                if ($annotation instanceof \Gedmo\Mapping\Annotation\TreeParent) {
                    $this->gedmoTreeProperties['parent']['source'] = $property->name;
                    $errorParent = false;
                    $field = 'parent';
//                    $childrenField = false;
//                    foreach ($annotations as $parentAnnotation) {
//                        if ($parentAnnotation instanceof \Doctrine\ORM\Mapping\ManyToOne) {
//                            $childrenField = true;
//                            $childrenProperty = $metadata->getReflectionProperty( $parentAnnotation->inversedBy );
//                            if ($childrenProperty === null) {
//                                throw new \InvalidArgumentException('Children property not exists');
//                            }
//                            if ($childrenProperty->isPublic()) {
//                                $this->gedmoTreeProperties['children']['source'] = $childrenProperty->name;
//                            } elseif (!method_exists($metadata->name, $childrenProperty->name) &&
//                                method_exists($metadata->name, 'get' . $childrenProperty->name)) {
//                                $this->gedmoTreeProperties['children']['public'] = false;
//                                $this->gedmoTreeProperties['children']['source'] = 'get' . $childrenProperty->name;
//                            } else {
//                                throw new \InvalidArgumentException('Getter for children property not found');
//                            }
//                        }
//                    }
//                    if (!$childrenField) {
//                        throw new \InvalidArgumentException('Cannot find children property');
//                    }
                }
                if ($field && !$property->isPublic()) {
                    $this->gedmoTreeProperties[$field]['public'] = false;
                    if (!method_exists($metadata->name, $property->name) &&
                        method_exists($metadata->name, 'get' . $property->name)) {
                        $this->gedmoTreeProperties[$field]['source'] = 'get' . $property->name;
                    } else {
                        throw new \InvalidArgumentException(sprintf('Getter for %s property not found', $property->name));
                    }
                }
            }
        }
        if ($errorId) {
            throw new \InvalidArgumentException('Identifier field not found in your tree entity');
        }
        if ($errorParent) {
            throw new \InvalidArgumentException('Parent field not found in your tree entity');
        }
    }

    /**
     * Initializes the choices and returns them.
     *
     * If the entities were passed in the "choices" option, this method
     * does not have any significant overhead. Otherwise, if a query builder
     * was passed in the "query_builder" option, this builder is now used
     * to construct a query which is executed. In the last case, all entities
     * for the underlying class are fetched from the repository.
     *
     * @return array  An array of choices
     */
    protected function load()
    {
        parent::load();

        foreach ($this->getEntities() as $entity) {
            $id = $this->gedmoTreeProperties['id']['public'] ?
                $entity->{$this->gedmoTreeProperties['id']['source']} :
                $entity->{$this->gedmoTreeProperties['id']['source']}();

            $parent = $this->gedmoTreeProperties['parent']['public'] ?
                $entity->{$this->gedmoTreeProperties['parent']['source']} :
                $entity->{$this->gedmoTreeProperties['parent']['source']}();

            $parent = ($parent === null) ? null : (($this->gedmoTreeProperties['id']['public']) ?
                $parent->{$this->gedmoTreeProperties['id']['source']} :
                $parent->{$this->gedmoTreeProperties['id']['source']}());

            if (!$this->gedmoTreeProperties['roots']['show']) {
                if ($parent == null) {
                    unset($this->choices[$id]);
                    continue;
                }
                // filter by roots ids
                $parent = (!$this->gedmoTreeProperties['roots']['show'] &&
                    in_array($parent, $this->gedmoTreeProperties['roots']['ids'])) ? null : $parent;
            }

            $this->choices[$id] = array(
                'id' => $id,
                'parent' => $parent,
//                'hasChildren' => $this->gedmoTreeProperties['children']['public'] ?
//                    (count($entity->{$this->gedmoTreeProperties['children']['source']}) ? true : false) :
//                    (count($entity->{$this->gedmoTreeProperties['children']['source']}()) ? true : false),
                'title' => $this->choices[$id],
            );
        }

        return $this->choices;
    }

}
