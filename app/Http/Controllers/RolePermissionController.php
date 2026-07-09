<?php

namespace App\Http\Controllers;

use App\Services\RolePermissionService;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Exception;

class RolePermissionController extends Controller
{
    protected $service;

    public function __construct(RolePermissionService $service)
    {
        $this->service = $service;
    }

    public function createRole(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name'
        ]);

        try {
            $this->service->createRole($request->name);

            return $this->Success(['message' => 'Role created']);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Role creation failed']);
        }
    }

    public function createPermission(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name'
        ]);

        try {
            $this->service->createPermission($request->name);

            return $this->Success(['message' => 'Permission created']);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Permission creation failed']);
        }
    }

    // 
    public function syncPermissionsToRole(Request $request)
    {
        $request->validate([
            'role' => 'required|string|exists:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        try {
            $this->service->syncPermissionsToRole(
                $request->role,
                $request->input('permissions', [])
            );

            return $this->Success(['message' => 'Permissions synced']);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Sync permissions failed']);
        }
    }

    public function assignRoleToUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string|exists:roles,name',
        ]);

        try {
            $user = User::findOrFail($request->user_id);

            $this->service->assignRoleToUser($user, $request->role);

            return $this->Success(['message' => 'Role assigned']);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Assign role failed']);
        }
    }

    public function getAllRoles(Request $request)
    {
        try {
            $filters = $request->only(['search']);
            $perPage = (int) $request->get('per_page', 10);
            $roles = $this->service
                ->getAllRoles($filters)
                ->paginate($perPage);

            return $this->Success($roles);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Fetching roles failed']);
        }
    }

    public function getAllPermissions(Request $request)
    {
        try {
            $filters = $request->only(['search', 'category_id']);
            $perPage = (int) $request->get('per_page', 10);
            $permissions = $this->service
                ->getAllPermissions($filters)
                ->paginate($perPage);

            return $this->Success($permissions);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Fetching permissions failed']);
        }
    }

    public function getPermissionsToRole($roleName)
    {
        try {
            $role = $this->service->getPermissionsToRole($roleName);
            return $this->Success([
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'permissions_count' => $role->permissions->count(),
            ]);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Fetching role failed']);
        }
    }

    public function updatePermission(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name,' . $id,
        ]);

        try {
            $this->service->updatePermission($id, $request->name);

            return $this->Success(['message' => 'Permission updated']);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Update failed']);
        }
    }

    public function deletePermission($id)
    {
        try {
            $this->service->deletePermission($id);

            return $this->Success(['message' => 'Permission deleted']);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Delete failed']);
        }
    }

    public function changeUserRole(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string|exists:roles,name',
        ]);

        try {
            $user = User::findOrFail($request->user_id);

            $this->service->changeUserRole($user, $request->role);

            return $this->Success(['message' => 'User role changed']);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Change role failed']);
        }
    }

    public function createCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:permission_categories,name',
            'description' => 'nullable|string'
        ]);

        try {
            $this->service->createCategory($request->name, $request->description);

            return $this->Success(['message' => 'Category created']);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Category creation failed']);
        }
    }

    public function getAllCategories()
    {
        try {
            return $this->Success($this->service->getAllCategories());

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Fetching categories failed']);
        }
    }

    public function getPermissionsByCategory($categoryId = null)
    {
        try {
            $permissions = $this->service->getPermissionsByCategory($categoryId);

            return $this->Success($permissions);

        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Fetching permissions failed']);
        }
    }
}