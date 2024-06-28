<?php

namespace App\Models;

use App\Facades\MsgConnect;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

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
            // pas d acces à msgraph en local 
            \Log::inf('on est en local');
            return  [];
        }
        $connected = MsgConnect::isConnected();
        if (!$connected) {
            MsgConnect::connect(false);
        }
        \Log::info('connecté à ms graph');
        $users = MsgConnect::get('users');
        $users = $users['value'] ?? [];
        $existingEmails = MsgUser::pluck('email')->toArray();
        // \Log::info("filteredUsers");
        $filteredUsers = array_filter($users, function ($user) use ($existingEmails) {
            // \Log::info($user);
            return !in_array($user['mail'], $existingEmails);
        });
        return \Arr::pluck($filteredUsers, 'mail', 'id');
    }

    public static function getApiMsgUser($id)
    {
        $connected = MsgConnect::isConnected();
        if ($connected) {
            $users = MsgConnect::get('users');
            $users = collect($users['value'] ?? []);
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
        $apiResponse = $reponse['response'] ?? false;
        \Log::info('apiResponse');
        \Log::info($apiResponse);
        if($apiResponse['id'] ?? false) {
            $this->suscription_id = $apiResponse['id']; 
            $this->expire_at = Carbon::parse($apiResponse['expirationDateTime']);
            $this->save();
        } else {
            \Log::info('pas ok  apireponse ');
        }
        
    }

    public function revoke()
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
}
