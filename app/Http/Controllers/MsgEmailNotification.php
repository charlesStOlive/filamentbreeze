<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MsgEmailNotification extends Controller
{
    public function handle(Request $request)
    {
        $notifications = $request->input('value');
        \Log::info('notifications');
        \Log::info($notifications);
        
        $clientStateReceived = $request->header('Client-State');
        if ($clientStateReceived !== 'expectedClientState') {
            return response()->json(['error' => 'Invalid client state'], 403);
        }

        // Logique pour traiter la notification
        $notification = $request->all();
        Log::info('Email notification received: ', $notification);

        // Envoyer une réponse pour confirmer la réception de la notification
        return response()->json(['status' => 'success'], 200);
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
