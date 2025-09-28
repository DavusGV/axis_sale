<?php
namespace App\Http\Controllers;

use App\Services\RolePermissionService;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        DB::beginTransaction();
        try {
            $this->service->createRole($request->name);
            DB::commit();
            return $this->Success(['message' => 'Role created']);
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->BadRequest(['error' => 'Validation failed', 'messages' => $e->errors()]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Role creation failed', 'message' => $e->getMessage()]);
        }
    }

    public function createPermission(Request $request)
    {
        DB::beginTransaction();
        try {
            $this->service->createPermission($request->name);
            DB::commit();
            return $this->Success(['message' => 'Permission created']);
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->BadRequest(['error' => 'Validation failed', 'messages' => $e->errors()]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Permission creation failed', 'message' => $e->getMessage()]);
        }
    }

    public function assignPermissionsToRole(Request $request)
    {
        $request->validate([
            'role' => 'required|string|exists:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        DB::beginTransaction();
        try {
            $this->service->assignPermissionsToRole($request->role, $request->permissions);
            DB::commit();
            return $this->Success(['message' => 'Permissions assigned to role']);
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->BadRequest(['error' => 'Validation failed', 'messages' => $e->errors()]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Assigning permissions to role failed', 'message' => $e->getMessage()]);
        }
    }

    public function assignRoleToUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role' => 'required|string|exists:roles,name',
        ]);

        DB::beginTransaction();
        try {
            $user = User::findOrFail($request->user_id);
            $this->service->assignRoleToUser($user, $request->role);
            DB::commit();
            return $this->Success(['message' => 'Role assigned to user']);
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->BadRequest(['error' => 'Validation failed', 'messages' => $e->errors()]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Assigning role to user failed', 'message' => $e->getMessage()]);
        }
    }

    public function getAllRoles()
    {
        try {
            return $this->Success($this->service->getAllRoles());
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Fetching roles failed', 'message' => $e->getMessage()], 500);
        }
    }

    public function getAllPermissions()
    {
        try {
            return $this->Success($this->service->getAllPermissions());
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Fetching permissions failed', 'message' => $e->getMessage()], 500);
        }
    }

}
