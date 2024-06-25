<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgEmailIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'msg_user_id',
        'data',
        'status',
    ];

    public function msg_email_user()
    {
        return $this->belongsTo(MsgUser::class);
    }
}
