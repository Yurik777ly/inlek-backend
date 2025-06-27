<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Category\CategoryService;

use App\Models\ProductCategoryView;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $CategoryService,
    ) {}

    public function getList(Request $request, int $id=2): JsonResponse
    {
        $categories = $this->CategoryService->getCategories($id);
        return $this->responseOk($categories);
    }

    public function getReleaseForms(Request $request, int $id=2): JsonResponse
    {
        $forms = $this->CategoryService->getForms($id);
        return $this->responseOk($forms);
    }

    public function getBrands(Request $request, int $id=2): JsonResponse
    {
        $manufacturers = $this->CategoryService->getBrands($id);
        return $this->responseOk($manufacturers);
    }

    public function getCountries(Request $request, int $id=2): JsonResponse
    {
        $countries = $this->CategoryService->getCountries($id);
        return $this->responseOk($countries);
    }
}
