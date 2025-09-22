<?php

namespace App\Http\Controllers\API\Push;

use App\Services\Firebase\FirebaseService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }


    public function sendNotification(Request $request)
    {
        $request->validate([
            'device_token' => 'required|string',
            'title' => 'required|string',
            'body' => 'required|string',
        ]);
        $result = $this->firebaseService->sendToDevice(
            $request->device_token,
            [
                'title' => $request->title,
                'body' => $request->body,
            ]
        );

        return response()->json($result);
    }
}