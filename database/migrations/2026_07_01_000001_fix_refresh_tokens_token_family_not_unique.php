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
        if (DB::getDriverName() === 'pgsql') {
            // Production DBs created by Prisma use Prisma naming; fresh Laravel installs use Laravel naming.
            $exists = fn(string $name) => DB::selectOne(
                "SELECT 1 FROM pg_indexes WHERE tablename = 'refresh_tokens' AND indexname = ?",
                [$name]
            );

            if ($exists('RefreshToken_tokenFamily_key')) {
                DB::statement('DROP INDEX "RefreshToken_tokenFamily_key"');
            } elseif ($exists('refresh_tokens_token_family_unique')) {
                DB::statement('DROP INDEX "refresh_tokens_token_family_unique"');
            }
        } else {
            Schema::table('refresh_tokens', function (Blueprint $table) {
                $table->dropUnique(['token_family']);
            });
        }

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
