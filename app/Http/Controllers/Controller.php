<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
    public function __construct() {

    }

    public function InternalError($result = null)
    {
        $response["code"]    = 500;
        $response["message"] = "Internal error.";
        if (!empty($result)) {
            $response["data"]    = $result;
        }
        return response()->json($response, 500);
    }
    public function BadRequest($result = null)
    {
        $response["code"]    = 400;
        $response["message"] = "Bad Request.";
        if (!empty($result)) {
            $response["data"]    = $result;
        }
        return response()->json($response, 400);
    }
    public function NoContent($result = null)
    {
        $response["code"]    = 204;
        $response["message"] = "No Content";
        if (!empty($result)) {
            $response["data"]    = $result;
        }
        return response()->json($response);
    }
    public function Success($result)
    {
        $response["code"]    = 200;
        $response["message"] = "Success.";
        $response["data"]    = $result;
        return response()->json($response);
    }
    public function Unauthorized($result = null)
    {
        $response["code"]    = 401;
        $response["message"] = "Unauthorized";
        if (!empty($result)) {
            $response["data"]    = $result;
        }
        return response()->json($response, 401);
    }
}
