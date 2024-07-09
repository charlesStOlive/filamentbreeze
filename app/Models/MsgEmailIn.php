<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MsgEmailIn extends Model
{
    use HasFactory;

    protected $protected = ['id'];

    protected $casts = [
        'data_mail' => 'array',
        'tos' => 'array',
        'data_sellsy' => 'array'
    ];

    public function msg_email_user()
    {
        return $this->belongsTo(MsgUser::class);
    }
}
