<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgEmailIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'msg_user_id',
        'from',
        'data',
        'status',
        'status_message',
        'has_client',
        'has_contact',
        'has_score',
        'score',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function msg_email_user()
    {
        return $this->belongsTo(MsgUser::class);
    }
}
