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
        Schema::table('msg_email_ins', function (Blueprint $table) {
            $table->boolean('has_client')->default(0)->after('data');
            $table->boolean('has_contact')->default(0)->after('has_client');
            $table->boolean('has_score')->default(0)->after('has_contact');
            $table->integer('score')->nullable()->after('has_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('msg_email_ins', function (Blueprint $table) {
            $table->dropColumn('has_client');
            $table->dropColumn('has_contact');
            $table->dropColumn('has_score');
            $table->dropColumn('score');
        });
    }
};
