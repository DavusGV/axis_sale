<?php
namespace App\Http\Controllers;
use App\Services\CategoryService;
use Exception;

class CategoryController extends Controller
{
    protected $categoryService;

    public function __construct(CategoryService $category)
    {
        $this->categoryService = $category;
    }

    public function index()
    {
        try {
            $categories = $this->categoryService->getAll();
            return $this->Success(['categories' => $categories]);
        } catch (Exception $e) {
            return $this->InternalError(['error' => 'Error fetching categories', 'message' => $e->getMessage()]);
        }
    }
}
