<?php
/**
 * Filename: 2026_11_20_000001_create_postlinkly_tables.php
 * Author: Krafty Sprouts Media, LLC
 * Description: Creates Postlinkly's three tables: the site index, the stem
 *              counts per language, and the suggestions waiting in the editor.
 *
 * @package Postlinkly
 * @since   1.0.0
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the tables. Teme runs only up(), never down().
     *
     * @since 1.0.0
     */
    public function up(): void
    {
        // One row per content item Postlinkly has heard of. The integer key
        // exists because the suggestion engine identifies items by integer,
        // while Teme's IDs are 26-character strings.
        Schema::create('postlinkly_items', function (Blueprint $table): void {
            $table->bigIncrements('key');
            $table->string('item_id', 26)->unique();
            $table->string('type', 32);
            $table->string('language', 35);
            $table->text('title');
            $table->boolean('public')->default(false);
            $table->json('words')->nullable();
            $table->unsignedInteger('word_total')->default(0);
            $table->string('revision_id', 26)->nullable();
            $table->boolean('stale')->default(true);
            $table->timestamp('updated_at')->nullable();
            $table->index(['language', 'public']);
            $table->index('stale');
        });

        // How many public items in a language hold each stem: the document
        // frequency the engine's TF-IDF scoring reads.
        Schema::create('postlinkly_stems', function (Blueprint $table): void {
            $table->string('language', 35);
            $table->string('stem', 191);
            $table->unsignedInteger('doc_count')->default(0);
            $table->primary(['language', 'stem']);
        });

        // Suggestions a Find links run left for the editor panel, until the
        // person uses or discards each. Settled rows are kept so a discarded
        // target isn't offered again.
        Schema::create('postlinkly_suggestions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('item_id', 26);
            $table->string('revision_id', 26);
            $table->string('key', 64);
            $table->string('target_id', 26);
            $table->text('phrase');
            $table->unsignedInteger('occurrence');
            $table->text('sentence');
            $table->text('label');
            $table->text('detail');
            $table->string('settled', 10)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['item_id', 'key']);
        });
    }
};
