<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // queues — most heavily queried (status counts, text_id lookups, monthly stats)
        Schema::table('queues', function (Blueprint $table) {
            $table->index('text_id', 'idx_queues_text_id');
            $table->index('status', 'idx_queues_status');
            $table->index(['text_id', 'status'], 'idx_queues_text_status');
            $table->index('created_at', 'idx_queues_created_at');
        });

        // texts — filtered by country, status, scheduled on every page load
        Schema::table('texts', function (Blueprint $table) {
            $table->index('status_id', 'idx_texts_status_id');
            $table->index('scheduled', 'idx_texts_scheduled');
            $table->index('created_by', 'idx_texts_created_by');
            $table->index('created_at', 'idx_texts_created_at');
            $table->index(['country_id', 'status_id'], 'idx_texts_country_status');
            $table->index(['country_id', 'scheduled'], 'idx_texts_country_scheduled');
        });

        // contact_lists — telephone lookups during bulk SMS sending
        Schema::table('contact_lists', function (Blueprint $table) {
            $table->index('contact_id', 'idx_contact_lists_contact_id');
            $table->index('is_active', 'idx_contact_lists_is_active');
            $table->index('telephone', 'idx_contact_lists_telephone');
        });

        // contacts — filtered by is_active on every send
        Schema::table('contacts', function (Blueprint $table) {
            $table->index('is_active', 'idx_contacts_is_active');
        });

        // country_user pivot — queried on every authenticated request
        Schema::table('country_user', function (Blueprint $table) {
            $table->index('user_id', 'idx_country_user_user_id');
        });

        // jobs — queue worker polls this table constantly
        Schema::table('jobs', function (Blueprint $table) {
            $table->index('available_at', 'idx_jobs_available_at');
            $table->index('reserved_at', 'idx_jobs_reserved_at');
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
