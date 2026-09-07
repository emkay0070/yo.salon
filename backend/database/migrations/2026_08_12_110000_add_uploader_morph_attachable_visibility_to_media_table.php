<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds three concepts to the media table:
     *
     * 1. Polymorphic uploader (uploader_type / uploader_id)
     *    Answers: "Who uploaded this file?"
     *    Supports User, SpecialistAccount, PortalAccount — any authenticatable model.
     *    The legacy uploaded_by (bigint → users) is kept for backward compatibility.
     *
     * 2. Polymorphic attachable (attachable_type / attachable_id)
     *    Answers: "What entity does this media belong to?"
     *    e.g. Specialist, Customer, Service, Salon, CustomerTimeline, Provider
     *    This is the authoritative ownership anchor for authorization decisions.
     *
     * 3. Visibility (public / authenticated / private)
     *    Answers: "Who can access this media?"
     *    PUBLIC:        Served openly (profile photos, service images, branding)
     *    AUTHENTICATED: Any logged-in actor can read (non-sensitive shared assets)
     *    PRIVATE:       Only uploader/attachable owner (IDs, documents, sensitive photos)
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // --- Polymorphic uploader ---
            // Using nullable strings to support UUID and bigint primary keys
            $table->string('uploader_type')->nullable()->after('uploaded_by');
            $table->string('uploader_id')->nullable()->after('uploader_type');
            $table->index(['uploader_type', 'uploader_id'], 'media_uploader_morph_index');

            // --- Polymorphic attachable ---
            $table->string('attachable_type')->nullable()->after('uploader_id');
            $table->string('attachable_id')->nullable()->after('attachable_type');
            $table->index(['attachable_type', 'attachable_id'], 'media_attachable_morph_index');

            // --- Visibility ---
            if (DB::getDriverName() === 'sqlite') {
                $table->string('visibility')->default('public')->after('attachable_id');
            } else {
                $table->enum('visibility', ['public', 'authenticated', 'private'])
                    ->default('public')
                    ->after('attachable_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex('media_uploader_morph_index');
            $table->dropIndex('media_attachable_morph_index');
            $table->dropColumn(['uploader_type', 'uploader_id', 'attachable_type', 'attachable_id', 'visibility']);
        });
    }
};
