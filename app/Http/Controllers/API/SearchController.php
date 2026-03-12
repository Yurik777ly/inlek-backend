<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Category\CategoryService;
use App\Services\Product\ProductService;


class SearchController extends Controller
{
    public function __construct(
        private readonly CategoryService $CategoryService,
        private readonly ProductService $ProductService,
    ) {}

    public function getList(Request $request, int $id=2): JsonResponse
    {
        $categories = $this->CategoryService->getCategories($id);
        return $this->responseOk($categories);
    }

    public function getReleaseForms(Request $request, int $id=2): JsonResponse
    {
        $forms = $this->ProductService->getForms($id);
        return $this->responseOk($forms);
    }

    public function getManufacturers(Request $request, int $id=2): JsonResponse
    {
        $manufacturers = $this->ProductService->getManufacturers($id);
        return $this->responseOk($manufacturers);
    }
}
