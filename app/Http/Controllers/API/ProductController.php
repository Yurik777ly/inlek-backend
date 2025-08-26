<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Dto\Product\ProductDTO;
use App\Http\Dto\Profile\ProfileDTO;
use App\Models\SMS;
use App\Services\User\AuthService;
use App\Services\User\ProfileService;
use App\Services\Product\ProductService;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(
        protected readonly AuthService $AuthService,
        protected readonly ProfileService $ProfileService,
        protected readonly SMS $SMS,
        protected readonly CartService $CartService,
        protected readonly ProductService $ProductService
    ) {}

    public function getDaily(Request $request): JsonResponse
    {
        $data = $this->ProductService->getDailyProducts();
        return $this->responseOk(data: $data);
    }

    public function getById(Request $request, int $id): JsonResponse
    {
        $productDto = new ProductDTO(
            productId: $id
        );
        $data = $this->ProductService->getProductDetails($productDto);
        return $this->responseOk(data: $data);
    }

    public function getFilteredList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'price_from' => 'nullable|int',
            'price_to' => 'nullable|int',
            'release_form' => 'nullable|array',
            'form' => 'nullable|array',
            'brand' => 'nullable|array',
            'country' => 'nullable|array',
            'recipe' => 'nullable|string',
            'action' => 'nullable|boolean',
            'delivery' => 'nullable|string',
            'available' => 'nullable|boolean',
            'per_page' => 'nullable|int',
            'page' => 'nullable|int',
            'sortby' => 'nullable|string',
            'category_id' => 'nullable|int',
        ]);

        $productDto = new ProductDTO(
            priceFrom: $validated['price_from'] ?? 0,
            priceTo: $validated['price_to'] ?? 1000000,
            releaseForm: $validated['release_form'] ?? [],
            form: $validated['form'] ?? [],
            brand: $validated['brand'] ?? [],
            country: $validated['country'] ?? [],
            recipe: $request->boolean('recipe'),
            delivery: $request->boolean('delivery'),
            available: $request->boolean('available'),
            action: $request->boolean('action'),
            sortBy: $validated['sortby'] ?? 'price_desc',
            categoryId: $validated['category_id'] ?? null,
        );

        $products = $this->ProductService->getFilteredProducts($productDto, $validated['per_page'] ?? 20, $validated['page'] ?? 1);
        return $this->responseOk(data: $products);
    }

    public function getPharmaciesByProductId(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'geo_lat' => ['nullable', 'numeric'],
            'geo_long' => ['nullable', 'numeric'],
            'delivery' => 'nullable|array',
            'pharmacy_id' => 'nullable|int',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
        ]);

        $productDto = new ProductDTO(
            productId: $id,
            pharmacyId: $validated['pharmacy_id'] ?? null,
            pharmacyAddress: $validated['address'] ?? null,
            pharmacyDelivery: $validated['delivery'] ?? ['Доставка', 'Самовывоз'],
            geoLat: $validated['geo_lat'] ?? null,
            geoLong: $validated['geo_long'] ?? null,
        );

        if ($validated['geo_lat'] || $validated['geo_long']) {
            $this->CartService->setUserGeo(
                $validated['geo_lat'] ?? null,
                $validated['geo_long'] ?? null
            );
        }

        $products = $this->ProductService->getPharmaciesByProductId($productDto);
        return $this->responseOk(data: $products);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getProfile(Request $request): JsonResponse
    {
        $profileDto = new ProfileDTO(
            userId: $request->user()->id,
            phone: $request->user()->phone
        );
        $profile = $this->ProfileService->getUserProfile($profileDto);
        return $this->responseOk($profile->toArray());
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['string', 'regex:/^\+375(25|29|33|44)\-\d{3}\-\d{2}\-\d{2}$/'],
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'gender' => 'nullable|string',
            'birthday' => 'nullable|string',
            'email' => 'nullable|string',
            'old_password' => 'nullable|string',
            'new_password' => 'nullable|string',
            'new_password_confirm' => 'nullable|string',
            'status_notifications' => 'nullable|string',
            'accept_policy' => 'string',
            'code' => 'nullable|string',
        ]);
        $profileDto = new ProfileDTO(
            userId: $request->user()->id,
            phone: $validated['phone'] ?? "",
            firstName: $validated['first_name'] ?? null,
            lastName: $validated['last_name'] ?? null,
            gender: $validated['gender'] ?? 'male',
            birthday: $validated['birthday'] ?? null,
            email: $validated['email'] ?? null,
            oldPassword: $validated['old_password'] ?? null,
            newPassword: $validated['new_password'] ?? null,
            newPasswordConfirm: $validated['new_password_confirm'] ?? null,
            statusNotifications: $validated['status_notifications'] ?? false,
            acceptPolicy: $validated['accept_policy'] ?? false,
            code: $validated['code'] ?? null
        );
        $dataUser = $this->AuthService->getUser($profileDto);
        if ($dataUser && $request->user()->phone !== $dataUser->phone) {
            throw ValidationException::withMessages([
                'phone' => 'номер телефона занят',
            ]);
        }
        $data = $this->ProfileService->updateProfile($profileDto);
        if ($data['success']) {
            $profile = $this->ProfileService->getUserProfile($profileDto);
            return $this->responseOk($profile->toArray());
        } else {
            unset($data['success']);
            return $this->response(data: $data, code: 409);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteProfile(Request $request): JsonResponse
    {
        $this->ProfileService->deleteProfile($request->user());
        return $this->responseOk();
    }

}
