<?php

namespace App\Models;

use App\Facades\MsgConnect;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use App\Clases\EmailAnalyser;

class MsgUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'ms_id',
        'email',
        'abn_secret',
        'suscription_id',
        'expire_at'
    ];

    public function msg_email_ins()
    {
        return $this->hasMany(MsgEmailIn::class);
    }

    public static function getApiMsgUsersIdsEmails()
    {
        \Log::info('getApiMsgUsersIdsEmails'); 

        if (App::environment('local')) {
            \Log::info('on est en local');
            return [];
        }
        
        $connected = MsgConnect::isConnected();
        \Log::info('Is connected: ' . json_encode($connected));
        
        if (!$connected) {
            MsgConnect::connect(false);
        }

        $users = MsgConnect::getUsers();
        \Log::info('Users fetched from MS Graph API: ' . json_encode($users));
        
        $users = $users['value'] ?? [];
        $existingEmails = MsgUser::pluck('email')->toArray();
        \Log::info("Existing emails: " . json_encode($existingEmails));
        
        $filteredUsers = array_filter($users, function ($user) use ($existingEmails) {
            return !in_array($user['mail'], $existingEmails);
        });
        
        \Log::info('Filtered users: ' . json_encode($filteredUsers));
        return \Arr::pluck($filteredUsers, 'mail', 'id');
    }

    public static function getApiMsgUser($id)
    {
        $connected = MsgConnect::isConnected();
        \Log::info('Is connected: ' . json_encode($connected));
        
        if ($connected) {
            $users = MsgConnect::getUsers();
            $users = collect($users['value'] ?? []);
            \Log::info('Users: ' . json_encode($users));
            return $users->where('id', $id)->first();
        } else {
            return [];
        }
    }

    public function suscribe()
    {
        $reponse = MsgConnect::subscribeToEmailNotifications($this->ms_id, $this->abn_secret);
        \Log::info('reponse du suscribe');
        \Log::info($reponse);
        if($reponse['response']['id'] ?? false) {
            $this->suscription_id = $reponse['response']['id']; 
            $this->expire_at = Carbon::parse($reponse['response']['expirationDateTime']);
            $this->save();
        } else {
            \Log::info('pas ok  apireponse ',$reponse);
        }
        
    }

    public function revokeSuscription()
    {
        $reponse = MsgConnect::unsubscribeFromEmailNotifications($this->suscription_id);
        \Log::info('reponse du unsuscribe');
        \Log::info($reponse);
        if($reponse['success'] ?? false) {
            $this->suscription_id = null;
            $this->expire_at = null;
            $this->save();
        } else {
            \Log::info('pas de sucess ???  ');
        }
    }

    public function refreshSuscription()
    {
        $reponse = MsgConnect::renewEmailNotificationSubscription($this->suscription_id);
        \Log::info('reponse du refresh');
        \Log::info($reponse);
        if($reponse['success'] ?? false) {
            $this->expire_at = Carbon::parse($reponse['response']['expirationDateTime']);
            $this->save();
        } else {
            \Log::info('pas de sucess ???  ' ,$reponse);
        }
    }

    public function analyseEmail($email) {


    }

    
}
