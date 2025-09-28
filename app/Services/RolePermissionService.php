<?php
namespace App\Services;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionService
{
    public function createRole(string $name)
    {
        return Role::create(['name' => $name]);
    }

    public function createPermission(string $name)
    {
        return Permission::create(['name' => $name]);
    }

    public function assignPermissionToRole(string $roleName, string $permissionName)
    {
        $role = Role::findByName($roleName);
        $permission = Permission::findByName($permissionName);
        return $role->givePermissionTo($permission);
    }

    public function assignPermissionsToRole(string $roleName, array $permissions)
    {
        $role = Role::findByName($roleName);
        $role->syncPermissions($permissions);
        return $role;
    }

    public function assignRoleToUser($user, string $roleName)
    {
        $role = Role::findByName($roleName);
        return $user->assignRole($role);
    }

    public function getAllRoles()
    {
        return Role::all();
    }

    public function getAllPermissions()
    {
        return Permission::all();
    }
}
