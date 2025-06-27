<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class SearchController extends Controller
{
    public function __construct(
    ) {}

    public function getPopular(): JsonResponse
    {
        return $this->responseOk();
    }
}
