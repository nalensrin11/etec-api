<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('instructor_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->string('join_code')->nullable()->unique()->after('code');
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instructor_id');
            $table->dropUnique(['join_code']);
            $table->dropColumn('join_code');
        });
    }
};
