<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\PermissionCategory;
use App\Models\Category;
use Exception;

class RolePermissionService
{
    public function createRole(string $name)
    {
        DB::beginTransaction();
        try {
            $role = Role::create(['name' => $name]);

            DB::commit();
            return $role;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function createPermission(string $name)
    {
        DB::beginTransaction();
        try {
            $permission = Permission::create(['name' => $name]);

            DB::commit();
            return $permission;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function createCategory(string $name, ?string $descripcion = null)
    {
        DB::beginTransaction();
        try {
            $category = Category::create([
                'name' => $name,
                'descripcion' => $descripcion
            ]);

            DB::commit();
            return $category;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // METODO PARA ASIGNAR PERMISOS A UN ROL, SE PUEDE USAR PARA ACTUALIZAR LOS PERMISOS DE UN ROL EXISTENTE
    public function syncPermissionsToRole(string $roleName, array $permissions)
    {
        DB::beginTransaction();
        try {
            $role = Role::findByName($roleName);
            $role->syncPermissions($permissions);

            DB::commit();
            return $role;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function assignRoleToUser($user, string $roleName)
    {
        DB::beginTransaction();
        try {
            $user->syncRoles([$roleName]);

            DB::commit();
            return $user;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getAllRoles(array $filters = [])
    {
        $query = Role::query()->orderBy('name');

        if (!empty($filters['search'])) {
            $search = strtolower(trim($filters['search']));
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
        }

        return $query;
    }

    public function getAllPermissions(array $filters = [])
    {
        $query = Permission::query()->orderBy('name');

        if (!empty($filters['search'])) {
            $search = strtolower(trim($filters['search']));
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        return $query;
    }

    public function getAllCategories()
    {
        return PermissionCategory::all();
    }

    public function getPermissionsByCategory(?int $categoryId = null)
    {
        $query = Permission::with('category');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        return $query->orderBy('name')->get();
    }

    public function getPermissionsToRole(string $roleName)
    {
        return Role::where('name', $roleName)
            ->with('permissions')
            ->first();
    }

    public function updatePermission(int $id, string $newName)
    {
        DB::beginTransaction();
        try {
            $permission = Permission::findById($id);

            $permission->name = $newName;
            $permission->save();

            DB::commit();
            return $permission;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deletePermission(int $id)
    {
        DB::beginTransaction();
        try {
            Permission::findById($id)->delete();

            DB::commit();
            return true;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function changeUserRole($user, string $newRoleName)
    {
        DB::beginTransaction();
        try {
            $user->syncRoles([$newRoleName]);

            DB::commit();
            return $user;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getCategoriesWithGroupedPermissions()
    {
        return PermissionCategory::with([
            'permissions' => function ($query) {
                $query->orderBy('name');
            }
        ])->orderBy('name')->get();
    }
}