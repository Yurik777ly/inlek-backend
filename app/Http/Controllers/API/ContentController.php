<?php

namespace App\Http\Controllers\API;

use App\Services\Action\ActionService;
use App\Services\News\NewsService;
use App\Services\Article\ArticleService;
use App\Services\Content\ContentService;
use App\Services\Pharmacy\PharmacyService;
use App\Services\Banner\BannerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function __construct(
        protected readonly ActionService   $ActionService,
        protected readonly NewsService     $NewsService,
        protected readonly ArticleService  $ArticleService,
        protected readonly ContentService  $ContentService,
        protected readonly PharmacyService $PharmacyService,
        protected readonly BannerService   $BannerService,
    ) {}

    /**
     * @param Request $request
     * @return JsonResponse
     */
     public function getActions(Request $request): JsonResponse
     {
         $actions = $this->ActionService->getActionsList();
         return $this->responseOk($actions);
     }

    /**
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
     public function getActionById(Request $request, int $id): JsonResponse
     {
        $action = $this->ActionService->getActionById($id);
        return $this->responseOk($action);
     }

    /**
     * @param Request $request
     * @return JsonResponse
     */
     public function getNews(Request $request): JsonResponse
     {
         $news = $this->NewsService->getNewsList();
         return $this->responseOk($news);
     }

    /**
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
     public function getNewsById(Request $request, int $id): JsonResponse
     {
        $news = $this->NewsService->getNewsById($id);
        return $this->responseOk($news);
     }
    /**
     * @param Request $request
     * @return JsonResponse
     */
     public function getArticles(Request $request): JsonResponse
     {
         $articles = $this->ArticleService->getArticleList();
         return $this->responseOk($articles);
     }

    /**
     * @param Request $request
     * @return JsonResponse
     */
     public function getArticleById(Request $request, int $id): JsonResponse
     {
        $article = $this->ArticleService->getArticleById($id);
        return $this->responseOk($article);
     }

    /**
     * @param Request $request
     * @return JsonResponse
     */
     public function about(Request $request): JsonResponse
     {
        $id = env('article_about');
        $article = $this->ContentService->getContentById($id);
        return $this->responseOk($article);
     }

    /**
     * @param Request $request
     * @return JsonResponse
     */
     public function getPharmacies(Request $request): JsonResponse
     {
        $address = $request->get('address');
        $pharmacies = $this->PharmacyService->getPharmacies($address);
        return $this->responseOk($pharmacies);
     }

    /**
     * @param Request $request
     * @return JsonResponse
     */
     public function getBanners(Request $request): JsonResponse
     {
        $banners = $this->BannerService->getBanners();
        return $this->responseOk($banners);
     }

     /**
     * @param Request $request
     * @return JsonResponse
     */
     public function getCities(Request $request): JsonResponse
     {
      return $this->responseOk($this->ContentService->getCities());
     }

     /**
     * @param Request $request
     * @return JsonResponse
     */
     public function getCustomerInfo(Request $request): JsonResponse
     {
      return $this->responseOk($this->ContentService->getCustomerInfo());
     }
}
