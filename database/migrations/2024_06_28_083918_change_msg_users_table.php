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
        Schema::table('msg_users', function (Blueprint $table) {
            $table->string('suscription_id')->nullable()->after('email');
            $table->boolean('is_test')->default(1);
            $table->dropColumn('abn_state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('msg_users', function (Blueprint $table) {
            $table->dropColumn('suscription_id');
            $table->dropColumn('is_test');
            $table->string('abn_state')->nullable();
        });
    }
};
