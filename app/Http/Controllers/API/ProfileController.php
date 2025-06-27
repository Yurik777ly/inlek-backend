<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Dto\Profile\ProfileDTO;
use App\Models\SMS;
use App\Services\User\AuthService;
use App\Services\User\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function __construct(
        protected readonly AuthService $AuthService,
        protected readonly ProfileService $ProfileService,
        protected readonly SMS $SMS,
    ) {}

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
