<?php

namespace App\Models;

use App\Facades\MsgConnect;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MsgUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'ms_id',
        'email',
        'abn_state',
        'abn_secret',
    ];

    public function msg_email_ins()
    {
        return $this->hasMany(MsgEmailIn::class);
    }

    public static function getApiMsgUsersIdsEmails()
    {
        if (App::environment('local')) {
            // pas d acces à msgraph en local 
            return  [];
        }
        $connected = MsgConnect::isConnected();
        if (!$connected) {
            MsgConnect::connect(false);
        }
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
    }
}
