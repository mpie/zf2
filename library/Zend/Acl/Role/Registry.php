<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Acl
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/**
 * @namespace
 */
namespace Zend\Acl\Role;

use Zend\Acl\Role;
use Zend\Acl;

/**
 * @uses       Zend\Acl\Role
 * @uses       Zend\Acl\Role\Exception
 * @category   Zend
 * @package    Zend_Acl
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Registry
{
    /**
     * Internal Role registry data storage
     *
     * @var array
     */
    protected $_roles = array();

    /**
     * Adds a Role having an identifier unique to the registry
     *
     * The $parents parameter may be a reference to, or the string identifier for,
     * a Role existing in the registry, or $parents may be passed as an array of
     * these - mixing string identifiers and objects is ok - to indicate the Roles
     * from which the newly added Role will directly inherit.
     *
     * In order to resolve potential ambiguities with conflicting rules inherited
     * from different parents, the most recently added parent takes precedence over
     * parents that were previously added. In other words, the first parent added
     * will have the least priority, and the last parent added will have the
     * highest priority.
     *
     * @param  Zend\Acl\Role              $role
     * @param  Zend\Acl\Role|string|array $parents
     * @throws Zend\Acl\Exception\InvalidArgumentException
     * @return Zend\Acl\Role\Registry Provides a fluent interface
     */
    public function add(Role $role, $parents = null)
    {
        $roleId = $role->getRoleId();

        if ($this->has($roleId)) {
            throw new Acl\Exception\InvalidArgumentException("Role id '$roleId' already exists in the registry");
        }

        $roleParents = array();

        if (null !== $parents) {
            if (!is_array($parents)) {
                $parents = array($parents);
            }
            foreach ($parents as $parent) {
                try {
                    if ($parent instanceof Role) {
                        $roleParentId = $parent->getRoleId();
                    } else {
                        $roleParentId = $parent;
                    }
                    $roleParent = $this->get($roleParentId);
                } catch (Exception $e) {
                    throw new Acl\Exception\InvalidArgumentException("Parent Role id '$roleParentId' does not exist", 0, $e);
                }
                $roleParents[$roleParentId] = $roleParent;
                $this->_roles[$roleParentId]['children'][$roleId] = $role;
            }
        }

        $this->_roles[$roleId] = array(
            'instance' => $role,
            'parents'  => $roleParents,
            'children' => array(),
        );

        return $this;
    }

    /**
     * Returns the identified Role
     *
     * The $role parameter can either be a Role or a Role identifier.
     *
     * @param  Zend\Acl\Role|string $role
     * @throws Zend\Acl\Exception\InvalidArgumentException
     * @return Zend\Acl\Role
     */
    public function get($role)
    {
        if ($role instanceof Role) {
            $roleId = $role->getRoleId();
        } else {
            $roleId = (string) $role;
        }

        if (!$this->has($role)) {
            throw new Acl\Exception\InvalidArgumentException("Role '$roleId' not found");
        }

        return $this->_roles[$roleId]['instance'];
    }

    /**
     * Returns true if and only if the Role exists in the registry
     *
     * The $role parameter can either be a Role or a Role identifier.
     *
     * @param  Zend\Acl\Role|string $role
     * @return boolean
     */
    public function has($role)
    {
        if ($role instanceof Role) {
            $roleId = $role->getRoleId();
        } else {
            $roleId = (string) $role;
        }

        return isset($this->_roles[$roleId]);
    }

    /**
     * Returns an array of an existing Role's parents
     *
     * The array keys are the identifiers of the parent Roles, and the values are
     * the parent Role instances. The parent Roles are ordered in this array by
     * ascending priority. The highest priority parent Role, last in the array,
     * corresponds with the parent Role most recently added.
     *
     * If the Role does not have any parents, then an empty array is returned.
     *
     * @uses   Zend\Acl\Role\Registry::get()
     * @param  Zend\Acl\Role|string $role
     * @return array
     */
    public function getParents($role)
    {
        $roleId = $this->get($role)->getRoleId();

        return $this->_roles[$roleId]['parents'];
    }

    /**
     * Returns true if and only if $role inherits from $inherit
     *
     * Both parameters may be either a Role or a Role identifier. If
     * $onlyParents is true, then $role must inherit directly from
     * $inherit in order to return true. By default, this method looks
     * through the entire inheritance DAG to determine whether $role
     * inherits from $inherit through its ancestor Roles.
     *
     * @param  Zend\Acl\Role|string $role
     * @param  Zend\Acl\Role|string $inherit
     * @param  boolean                        $onlyParents
     * @throws Zend\Acl\Exception\InvalidArgumentException
     * @return boolean
     */
    public function inherits($role, $inherit, $onlyParents = false)
    {
        try {
            $roleId    = $this->get($role)->getRoleId();
            $inheritId = $this->get($inherit)->getRoleId();
        } catch (Exception $e) {
            throw new Acl\Exception\InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        $inherits = isset($this->_roles[$roleId]['parents'][$inheritId]);

        if ($inherits || $onlyParents) {
            return $inherits;
        }

        foreach ($this->_roles[$roleId]['parents'] as $parentId => $parent) {
            if ($this->inherits($parentId, $inheritId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes the Role from the registry
     *
     * The $role parameter can either be a Role or a Role identifier.
     *
     * @param  Zend\Acl\Role|string $role
     * @throws Zend\Acl\Exception\InvalidArgumentException
     * @return Zend\Acl\Role\Registry Provides a fluent interface
     */
    public function remove($role)
    {
        try {
            $roleId = $this->get($role)->getRoleId();
        } catch (Exception $e) {
            throw new Acl\Exception\InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }

        foreach ($this->_roles[$roleId]['children'] as $childId => $child) {
            unset($this->_roles[$childId]['parents'][$roleId]);
        }
        foreach ($this->_roles[$roleId]['parents'] as $parentId => $parent) {
            unset($this->_roles[$parentId]['children'][$roleId]);
        }

        unset($this->_roles[$roleId]);

        return $this;
    }

    /**
     * Removes all Roles from the registry
     *
     * @return Zend\Acl\Role\Registry Provides a fluent interface
     */
    public function removeAll()
    {
        $this->_roles = array();

        return $this;
    }

    /**
     * Get all roles in the registry
     * 
     * @return array
     */
    public function getRoles()
    {
        return $this->_roles;
    }
}
