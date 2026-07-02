<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// token_family identifies a chain of rotated tokens and must be shared across rows.
// The original unique constraint prevented rotation after the first use.
// The constraint was created by Prisma and uses its own naming convention.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX "RefreshToken_tokenFamily_key"');

        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->index('token_family');
        });
    }

    public function down(): void
    {
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->dropIndex(['token_family']);
            $table->unique('token_family');
        });
    }
};
