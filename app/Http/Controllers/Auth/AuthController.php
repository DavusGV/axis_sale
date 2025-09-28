<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\DTOs\AuthDTO;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Exception;


class AuthController extends Controller
{
    protected $authService;
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function register(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = AuthDTO::validate($request->all(), 'register');
            $token = $this->authService->register($data);
            DB::commit();
            return $this->Success(['token' => $token, 'user' => $data]);
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->BadRequest(['error' => 'Validation failed', 'messages' => $e->errors()]);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->InternalError(['error' => 'Registration failed', 'message' => $e->getMessage()]);
        }
    }

    public function login(Request $request)
    {
        try {
            $data = AuthDTO::validate($request->all(), 'login');
            $token = $this->authService->login($data);
            if (!$token) {
                return $this->Unauthorized();
            }
            return $this->Success(['token' => $token, 'user' => Auth::user()]);
        } catch (ValidationException $e) {
            return $this->BadRequest(['error' => 'Validation failed', 'messages' => $e->errors()]);
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Login failed', 'message' => $e->getMessage()]);
        }
    }

    public function logout(Request $request)
    {
        try {
            if ($request->user()) {
                $request->user()->tokens()->delete();
                return $this->Success(['message' => 'Logged out']);
            }
            return $this->Unauthorized();
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Logout failed', 'message' => $e->getMessage()]);
        }
    }
}
