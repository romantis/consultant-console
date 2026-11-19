<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handbook_exports', function (Blueprint $table) {
            $table->string('public_slug', 64)->nullable()->after('download_url');
            $table->timestamp('published_at')->nullable()->after('expires_at');
            $table->foreignUlid('published_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
        });

        $this->backfillPublicSlugs();

        Schema::table('handbook_exports', function (Blueprint $table) {
            $table->unique('public_slug');
        });
    }

    public function down(): void
    {
        Schema::table('handbook_exports', function (Blueprint $table) {
            $table->dropUnique('handbook_exports_public_slug_unique');
            $table->dropConstrainedForeignId('published_by');
            $table->dropColumn(['published_at', 'public_slug']);
        });
    }

    private function backfillPublicSlugs(): void
    {
        DB::table('handbook_exports')
            ->select('id')
            ->orderBy('id')
            ->lazy()
            ->each(function ($export) {
                DB::table('handbook_exports')
                    ->where('id', $export->id)
                    ->update([
                        'public_slug' => $this->generateUniqueSlug(),
                    ]);
            });
    }

    private function generateUniqueSlug(): string
    {
        do {
            $slug = Str::lower((string) Str::ulid());
        } while (
            DB::table('handbook_exports')
                ->where('public_slug', $slug)
                ->exists()
        );

        return $slug;
    }
};
