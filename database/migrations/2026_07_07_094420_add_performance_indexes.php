<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasIndex = fn(string $table, string $key): bool =>
            !empty(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$key]));

        Schema::table('queues', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('queues', 'idx_queues_text_id'))    $table->index('text_id', 'idx_queues_text_id');
            if (!$hasIndex('queues', 'idx_queues_status'))     $table->index('status', 'idx_queues_status');
            if (!$hasIndex('queues', 'idx_queues_text_status')) $table->index(['text_id', 'status'], 'idx_queues_text_status');
            if (!$hasIndex('queues', 'idx_queues_created_at')) $table->index('created_at', 'idx_queues_created_at');
        });

        Schema::table('texts', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('texts', 'idx_texts_status_id'))         $table->index('status_id', 'idx_texts_status_id');
            if (!$hasIndex('texts', 'idx_texts_scheduled'))         $table->index('scheduled', 'idx_texts_scheduled');
            if (!$hasIndex('texts', 'idx_texts_created_by'))        $table->index('created_by', 'idx_texts_created_by');
            if (!$hasIndex('texts', 'idx_texts_created_at'))        $table->index('created_at', 'idx_texts_created_at');
            if (!$hasIndex('texts', 'idx_texts_country_status'))    $table->index(['country_id', 'status_id'], 'idx_texts_country_status');
            if (!$hasIndex('texts', 'idx_texts_country_scheduled')) $table->index(['country_id', 'scheduled'], 'idx_texts_country_scheduled');
        });

        Schema::table('contact_lists', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('contact_lists', 'idx_contact_lists_contact_id')) $table->index('contact_id', 'idx_contact_lists_contact_id');
            if (!$hasIndex('contact_lists', 'idx_contact_lists_is_active'))  $table->index('is_active', 'idx_contact_lists_is_active');
            if (!$hasIndex('contact_lists', 'idx_contact_lists_telephone'))  $table->index('telephone', 'idx_contact_lists_telephone');
        });

        Schema::table('contacts', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('contacts', 'idx_contacts_is_active')) $table->index('is_active', 'idx_contacts_is_active');
        });

        Schema::table('country_user', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('country_user', 'idx_country_user_user_id')) $table->index('user_id', 'idx_country_user_user_id');
        });

        Schema::table('jobs', function (Blueprint $table) use ($hasIndex) {
            if (!$hasIndex('jobs', 'idx_jobs_available_at')) $table->index('available_at', 'idx_jobs_available_at');
            if (!$hasIndex('jobs', 'idx_jobs_reserved_at'))  $table->index('reserved_at', 'idx_jobs_reserved_at');
        });
    }

    public function down(): void
    {
        Schema::table('queues', function (Blueprint $table) {
            $table->dropIndex('idx_queues_text_id');
            $table->dropIndex('idx_queues_status');
            $table->dropIndex('idx_queues_text_status');
            $table->dropIndex('idx_queues_created_at');
        });

        Schema::table('texts', function (Blueprint $table) {
            $table->dropIndex('idx_texts_status_id');
            $table->dropIndex('idx_texts_scheduled');
            $table->dropIndex('idx_texts_created_by');
            $table->dropIndex('idx_texts_created_at');
            $table->dropIndex('idx_texts_country_status');
            $table->dropIndex('idx_texts_country_scheduled');
        });

        Schema::table('contact_lists', function (Blueprint $table) {
            $table->dropIndex('idx_contact_lists_contact_id');
            $table->dropIndex('idx_contact_lists_is_active');
            $table->dropIndex('idx_contact_lists_telephone');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('idx_contacts_is_active');
        });

        Schema::table('country_user', function (Blueprint $table) {
            $table->dropIndex('idx_country_user_user_id');
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->dropIndex('idx_jobs_available_at');
            $table->dropIndex('idx_jobs_reserved_at');
        });
    }
};
