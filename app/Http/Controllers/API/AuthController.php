<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Dto\Auth\AuthDTO;
use App\Services\User\AuthService;
use App\Services\SMS\SMSService;
use App\Models\SMS;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AuthController extends Controller
{
    public function __construct(
        protected readonly AuthService $AuthService,
        protected readonly SMSService $SMSService,
        protected readonly SMS $SMS,
    ) {}

    public function checkPhone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'regex:/^\+375(25|29|33|44)\-\d{3}\-\d{2}\-\d{2}$/'],
        ]);
        $authDto = new AuthDTO(
            phone: $validated['phone']
        );

        $user = $this->AuthService->getUser($authDto);
        if(!$user) {
            $data = 'not found';
        } else {
            $data = 'found';
        }

        return $this->responseOk(['phone' => $data]);

    }

   /**
     * @OA\Post(
     *      path="/auth/request-code",
     *      summary="Request SMS code",
     *      tags={"Auth"},
     *      @OA\RequestBody(
     *          required=true,
     *          description="Provide All Info Below",
     *          @OA\JsonContent(
     *              required={"phone"},
     *              @OA\Property(property="phone", type="string", format="text", example="+37525-222-33-44"),
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *      ),
     *      @OA\Response(
     *          response=409,
     *          description="Conflict",
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Wrong data",
     *      ),
     * )
     */
    public function requestCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'regex:/^\+375(25|29|33|44)[-]?\d{3}[-]?\d{2}[-]?\d{2}$/'],
        ]);
        $authDto = new AuthDTO(
            phone: $validated['phone']
        );
        $data = $this->SMSService->sendSMS($authDto);
        if (!$data['sent']) {
            return $this->response(data: ['error' => $data['error']], code: 409);
        }

        try {
            $sms = $this->SMS->query()->where('phone', $authDto->phone)->firstOrFail();
            if (! $sms->is_confirmed) {
                return $this->responseOk(['message' => 'SMS код отправлен', 'code' => $sms->code]);
            }
        } catch (ModelNotFoundException) {
        }
        return $this->responseOk(['message' => 'SMS код отправлен']);

    }

   /**
     * @OA\Post(
     *      path="/auth/registration",
     *      summary="Registration",
     *      tags={"Auth"},
     *      @OA\RequestBody(
     *          required=true,
     *          description="Provide All Info Below",
     *          @OA\JsonContent(
     *              required={"phone","code"},
     *              @OA\Property(property="phone", type="string", format="text", example="+37525-222-33-44"),
     *              @OA\Property(property="code", type="string", format="text", example="1234"),
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *      ),
     *      @OA\Response(
     *          response=409,
     *          description="Conflict",
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Wrong data",
     *      ),
     *     @OA\PathItem (
     *     ),
     * )
     */
    public function registration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'regex:/^\+375(25|29|33|44)\-\d{3}\-\d{2}\-\d{2}$/', 'unique:users'],
            'code' => ['required', 'string'],
        ]);
        $authDto = new AuthDTO(
            phone: $validated['phone'],
            code: $validated['code'],
        );
        $dataSMS = $this->SMSService->confirmCode($authDto);
        if (!$dataSMS['success']) {
            return $this->response(data: ['error' => $dataSMS['error']], code: 409);
        }
        $dataAuth = $this->AuthService->createUser($authDto);
        if (!$dataAuth['status']) {
            return $this->response(data: ['error' => $dataAuth['error']], code: 409);
        }
        return $this->responseOk();
    }


   /**
     * @OA\Post(
     *      path="/auth/update-password",
     *      summary="Update password on registration",
     *      tags={"Auth"},
     * @OA\RequestBody(
     *    required=true,
     *    description="Provide All Info Below",
     *    @OA\JsonContent(
     *       required={"phone","password"},
     *       @OA\Property(property="phone", type="string", format="text", example="+37525-222-33-44"),
     *       @OA\Property(property="password", type="string", format="text", example="admin"),
     *       @OA\Property(property="code", type="string", format="text", example="1234"),
     *    ),
     * ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *      ),
     *      @OA\Response(
     *          response=409,
     *          description="conflict",
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Wrong data",
     *      ),
    *   )
    */

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'regex:/^\+375(25|29|33|44)\-\d{3}\-\d{2}\-\d{2}$/'],
            'password' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);
        $authDto = new AuthDTO(
            phone: $validated['phone'],
            password: $validated['password'],
            code: $validated['code'],
        );
        $dataSMS = $this->SMSService->confirmCode($authDto);
        if (!$dataSMS['success']) {
            return $this->response(data: ['error' => $dataSMS['error']], code: 409);
        }
        $dataAuth = $this->AuthService->updatePassword($authDto);
        if (!$dataAuth['status']) {
            return $this->response(data: ['error' => $dataAuth['error']], code: 409);
        }
        return $this->responseOk();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    /**
     * @OA\Post(
     *      path="/auth/login",
     *      summary="Login",
     *      tags={"Auth"},
     * @OA\RequestBody(
     *    required=true,
     *    description="Provide All Info Below",
     *    @OA\JsonContent(
     *       required={"phone","password"},
     *       @OA\Property(property="phone", type="string", format="text", example="+37525-222-33-44"),
     *       @OA\Property(property="password", type="string", format="text", example="admin"),
     *    ),
     * ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *      ),
     *      @OA\Response(
     *          response=409,
     *          description="conflict",
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Wrong data",
     *      ),
     *   )
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'regex:/^\+375(25|29|33|44)\-\d{3}\-\d{2}\-\d{2}$/'],
            'password' => ['required'],
            'fcm_token' => ['string']
        ]);
        $authDto = new AuthDTO(
            phone:  $validated['phone'],
            password:  $validated['password'],
            fcm_token:  $validated['fcm_token'] ?? ''
        );
        $loginData = $this->AuthService->login($authDto);
        return $this->responseOk($loginData);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
   /**
     * @OA\Post(
     *      path="/auth/test",
     *      summary="Test",
     *      tags={"Auth"},
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Wrong data",
     *      ),
     *     @OA\PathItem (
     *     ),
     * )
     */
    public function test(Request $request): JsonResponse
    {
        // Получить текущего аутентифицированного пользователя ...
        //$user = Auth::user();
        //$user = $request->user();

        // Получить текущего аутентифицированного пользователя по идентификатору ...
        //$id = Auth::id();
        return $this->responseOk([$request->user()]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */

   /**
     * @OA\Post(
     *      path="/auth/logout",
     *      summary="Logout. NEED BEARER TOKEN",
     *      tags={"Auth"},
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Wrong data",
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthorized",
     *      ),
     *     @OA\PathItem (
     *     ),
     * )
     */

    public function logout(Request $request): JsonResponse
    {
        $this->AuthService->logout($request->user());
        return $this->responseOk();
    }
}
