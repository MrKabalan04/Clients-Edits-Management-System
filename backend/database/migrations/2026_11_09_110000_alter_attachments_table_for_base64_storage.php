<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('attachments')->exists()) {
            throw new RuntimeException(
                'Cannot remove attachments.file_path because attachment records already exist. '.
                'Migrate each referenced file into file_data and verify it before retrying.'
            );
        }

        Schema::table('attachments', function (Blueprint $table) {
            $table->longText('file_data')->after('file_name');
            $table->string('mime_type', 255)->after('file_data');
            $table->unsignedBigInteger('file_size')->after('mime_type');
            $table->dropColumn('file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('attachments')->exists()) {
            throw new RuntimeException(
                'Cannot roll back attachment storage while attachment records exist because file_data cannot be restored to file_path.'
            );
        }

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['file_data', 'mime_type', 'file_size']);
            $table->string('file_path', 500);
        });
    }
};
