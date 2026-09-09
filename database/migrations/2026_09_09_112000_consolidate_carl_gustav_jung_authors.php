<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConsolidateCarlGustavJungAuthors extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('authors') || ! Schema::hasTable('products')) {
            return;
        }

        DB::transaction(function () {
            $authors = DB::table('authors')
                ->whereIn('title', ['Carl Gustav Jung', 'GUSTAV JUNG CARL', 'GUSTAV JUNG KARL'])
                ->lockForUpdate()
                ->get();
            $canonical = $authors->first(fn ($author) => trim((string) $author->title) === 'Carl Gustav Jung');

            if (! $canonical) {
                return;
            }

            $duplicates = $authors->filter(function ($author) use ($canonical) {
                return (int) $author->id !== (int) $canonical->id
                    && in_array(trim((string) $author->title), ['GUSTAV JUNG CARL', 'GUSTAV JUNG KARL'], true);
            });
            $duplicateIds = $duplicates->pluck('id')->map(fn ($id) => (int) $id)->all();

            if (empty($duplicateIds)) {
                return;
            }

            DB::table('products')
                ->whereIn('author_id', $duplicateIds)
                ->update(['author_id' => (int) $canonical->id]);

            if (Schema::hasTable('product_actions')) {
                DB::table('product_actions')
                    ->where('group', 'author')
                    ->whereNotNull('links')
                    ->orderBy('id')
                    ->each(function ($action) use ($duplicateIds, $canonical) {
                        $links = json_decode((string) $action->links, true);

                        if (! is_array($links)) {
                            return;
                        }

                        $mapped = collect($links)
                            ->map(function ($link) use ($duplicateIds, $canonical) {
                                return is_numeric($link) && in_array((int) $link, $duplicateIds, true)
                                    ? (int) $canonical->id
                                    : $link;
                            })
                            ->unique(fn ($link) => is_scalar($link) ? (string) $link : serialize($link))
                            ->values()
                            ->all();

                        DB::table('product_actions')->where('id', $action->id)->update([
                            'links' => json_encode($mapped, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                    });
            }

            $updates = [
                'title' => 'Carl Gustav Jung',
                'meta_title' => 'Carl Gustav Jung',
                'slug' => 'carl-gustav-jung',
                'url' => trim((string) config('settings.author_path'), '/') . '/carl-gustav-jung',
                'status' => 1,
            ];

            if (Schema::hasColumn('authors', 'featured')) {
                $updates['featured'] = (int) $authors->max('featured');
            }

            if (Schema::hasColumn('authors', 'viewed')) {
                $updates['viewed'] = min(4294967295, (int) $authors->sum('viewed'));
            }

            DB::table('authors')->where('id', $canonical->id)->update($updates);
            DB::table('authors')->whereIn('id', $duplicateIds)->delete();
        }, 3);
    }

    public function down()
    {
        // Spajanje povezanih kataloških zapisa nije moguće sigurno automatski razdvojiti.
    }
}
