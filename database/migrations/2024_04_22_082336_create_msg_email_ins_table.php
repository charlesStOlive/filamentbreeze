<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('msg_email_ins', function (Blueprint $table) {
            $table->id();
            $table->string('msg_user_id')->nullable();
            $table->string('from')->nullable();
            $table->string('tos')->nullable();
            $table->json('data')->nullable();
            $table->boolean('has_client')->default(0);
            $table->boolean('has_contact')->default(0);
            $table->boolean('has_score')->default(0);
            $table->string('forwarded_to')->default(0);
            $table->integer('score')->nullable();
            $table->string('status')->nullable();
            $table->text('status_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msg_email_ins');
    }
};
