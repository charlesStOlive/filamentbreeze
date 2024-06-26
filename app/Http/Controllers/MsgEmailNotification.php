<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Facades\MsgConnect;

class MsgEmailNotification extends Controller
{
    public function handle(Request $request)
    {
        // \Log::info('Request data:');
        // \Log::info($request->all());
        // \Log::info('Request has validation data:');
        // \Log::info($request->has('validationToken'));

        // Check if the request contains a validation token
        if ($request->has('validationToken')) {
            \Log::info('Validation token received:');
            \Log::info($request->input('validationToken'));
            // Respond with the validation token as plain text
            return response($request->input('validationToken'))->header('Content-Type', 'text/plain');
        }

        $notificationData = $request->all();
        \Log::error('notificationData');
        \Log::error($notificationData);
    
        // Traitement de la notification
        try {
            $result = MsgConnect::processEmailNotification($notificationData);
            \Log::info('OK with result------------------------');
            return response()->json(['status' => 'success', 'message' => 'Email processed successfully'], 200);
        } catch (\Exception $e) {
            // \Log::error($e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed to process email: ' . $e->getMessage()], 500);
        }
    }

    // private function updateEmailSubject($accessToken, $userId, $messageId, $prefix)
    // {
    //     $getEmailResponse = Http::withHeaders([
    //         'Authorization' => 'Bearer ' . $accessToken,
    //     ])->get("https://graph.microsoft.com/v1.0/users/{$userId}/messages/{$messageId}");

    //     $email = $getEmailResponse->json();
    //     $updatedSubject = $prefix . $email['subject'];

    //     $updateResponse = Http::withHeaders([
    //         'Authorization' => 'Bearer ' . $accessToken,
    //         'Content-Type' => 'application/json'
    //     ])->patch("https://graph.microsoft.com/v1.0/users/{$userId}/messages/{$messageId}", [
    //         'subject' => $updatedSubject
    //     ]);

    //     return $updateResponse->json();
    // }
}
