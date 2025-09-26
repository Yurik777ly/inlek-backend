<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Dto\Profile\ProfileDTO;
use App\Models\SMS;
use App\Services\User\AuthService;
use App\Services\User\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\User\UpdateRequest;
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
     * @param UpdateRequest $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function updateProfile(UpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
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
