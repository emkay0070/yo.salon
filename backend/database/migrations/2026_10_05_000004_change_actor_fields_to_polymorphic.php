<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_service', function (Blueprint $table) {
            $table->dropForeign(['started_by_user_id']);
            $table->dropForeign(['completed_by_user_id']);
            $table->dropColumn(['started_by_user_id', 'completed_by_user_id']);

            $table->string('started_by_actor_type')->nullable();
            $table->string('started_by_actor_id')->nullable();
            $table->string('completed_by_actor_type')->nullable();
            $table->string('completed_by_actor_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('booking_service', function (Blueprint $table) {
            $table->dropColumn([
                'started_by_actor_type',
                'started_by_actor_id',
                'completed_by_actor_type',
                'completed_by_actor_id',
            ]);

            $table->unsignedBigInteger('started_by_user_id')->nullable();
            $table->unsignedBigInteger('completed_by_user_id')->nullable();
            $table->foreign('started_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('completed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
