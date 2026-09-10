<?php

namespace App\Models\Back\Catalog\Product;

use App\Models\Back\Catalog\Author;
use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Settings\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductHistory extends Model
{

    /**
     * @var string $table
     */
    protected $table = 'history_log';

    /**
     * @var array $guarded
     */
    protected $guarded = ['id'];

    /**
     * @var string
     */
    private $type;

    /**
     * @var array
     */
    private $new;

    /**
     * @var array|null
     */
    private $old;

    /**
     * @var string
     */
    private $changed = '';

    /**
     * @var string
     */
    private $title_column = 'name';

    /**
     * @var string
     */
    private $target = 'product';


    /**
     * ProductHistory constructor.
     *
     * @param array      $new
     * @param array|null $old
     */
    public function __construct(array $new, array $old = null)
    {
        $this->new = $new;
        $this->old = $old ?: null;

        $this->changed = '<b>' . $this->new[$this->title_column] . '</b><br><ul class="small">';
    }


    /**
     * @param string $type
     */
    public function addData(string $type)
    {
        $this->type = $type;

        if ($this->old) {
            $this->collectChangedValues();
        }

        $this->changed .= '</ul>';

        return $this->saveResponse();
    }


    /**
     * @return mixed
     */
    private function saveResponse()
    {
        return $this->insert([
            'user_id'    => auth()->user()->id,
            'type'       => $this->type,
            'target'     => $this->target,
            'target_id'  => $this->new['id'],
            'title'      => $this->resolveTitle(),
            'changes'    => $this->changed,
            'old_model'  => collect($this->old)->toJson(),
            'new_model'  => collect($this->new)->toJson(),
            'badge'      => 0,
            'comment'    => '',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }


    /**
     * @return string
     */
    private function resolveTitle(): string
    {
        if ( ! $this->old) {
            return '<span class="text-muted font-weight-lighter">Dodana je nova knjiga.</span> ' . Str::limit($this->new[$this->title_column], 40);
        }

        if ($this->changed != $this->new[$this->title_column]) {
            return '<span class="text-muted font-weight-lighter">Knjiga je editirana.</span> ' . Str::limit($this->new[$this->title_column], 40);
        }

        return '<span class="text-muted font-weight-lighter">Knjiga je snimljena bez promjene.</span> ' . Str::limit($this->new[$this->title_column], 40);
    }


    /**
     *
     */
    private function collectChangedValues(): void
    {
        // Author changed
        if ($this->old['author_id'] != $this->new['author_id']) {
            $old = Author::find($this->old['author_id']);
            $new = Author::find($this->new['author_id']);

            $this->changed .= '<li>Promjenjen autor: <b>' . (isset($old->title) ? $old->title : '(Nepoznat)') . '</b> u <b>' . (isset($new->title) ? $new->title : '(Nepoznat)') . '</b></li>' ;
        }

        // Publisher changed
        if ($this->old['publisher_id'] != $this->new['publisher_id']) {
            $old = Publisher::find($this->old['publisher_id']);
            $new = Publisher::find($this->new['publisher_id']);

            $this->changed .= '<li>Promjenjen nakladnik: <b>' . (isset($old->title) ? $old->title : '(Nepoznat)') . '</b> u <b>' . (isset($new->title) ? $new->title : '(Nepoznat)') . '</b></li>' ;
        }

        // Action changed
        if ($this->old['action_id'] != $this->new['action_id'] ||
            $this->old['special'] != $this->new['special'] ||
            $this->old['special_from'] != $this->new['special_from'] ||
            $this->old['special_to'] != $this->new['special_to']
        ) {
            if ($this->old['special'] != $this->new['special']) {
                $old = $this->old['special'] ? number_format($this->old['special'], 2, ',', '.') : 'prazno';
                $new = $this->new['special'] ? number_format($this->new['special'], 2, ',', '.') : 'prazno';
                $action_price = (' <b>' . $old . '</b> u <b>' . $new . '</b>');
            } else {
                $action_price = ' <b>nema promjene</b>';
            }

            $action_duration = '';

            if ($this->old['special_from'] != $this->new['special_from']) {
                $old = $this->old['special_from'] ? Carbon::make($this->old['special_from'])->format('d.m.Y') : 'neograničeno';
                $new = $this->new['special_from'] ? Carbon::make($this->new['special_from'])->format('d.m.Y') : 'neograničeno';
                $action_duration .= (' Od: <b>' . $old . '</b> u <b>' . $new . '</b>');
            } else {
                $action_duration .= ' Od: <b>nema promjene</b>';
            }

            if ($this->old['special_to'] != $this->new['special_to']) {
                $old = $this->old['special_to'] ? Carbon::make($this->old['special_to'])->format('d.m.Y') : 'neograničeno';
                $new = $this->new['special_to'] ? Carbon::make($this->new['special_to'])->format('d.m.Y') : 'neograničeno';
                $action_duration .= (' Do: <b>' . $old . '</b> u <b>' . $new . '</b>');
            } else {
                $action_duration .= ' Do: <b>nema promjene</b>';
            }


            $this->changed .= '<li>Promjenjena je akcija knjige.</li>';
            $this->changed .= '<ul><li>Akcijska cijena: ' . $action_price . '</li>';
            $this->changed .= '<li>Trajanje: ' . $action_duration . '</li></ul></li>';
        }

        // Name changed
        if ($this->old['name'] != $this->new['name']) {
            $this->changed .= '<li>Promjenjeno ime: <b>' . $this->old['name'] . '</b> u <b>' . $this->new['name'] . '</b></li>';
        }

        // Sku changed
        if ($this->old['sku'] != $this->new['sku']) {
            $this->changed .= '<li>Promjenjena šifra: <b>' . $this->old['sku'] . '</b> u <b>' . $this->new['sku'] . '</b></li>';
        }

        // Polica changed
        if ($this->old['polica'] != $this->new['polica']) {
            $this->changed .= '<li>Promjenjena polica: <b>' . $this->old['polica'] . '</b> u <b>' . $this->new['polica'] . '</b></li>';
        }

        // Description changed
        if ($this->old['description'] != $this->new['description']) {
            $this->changed .= '<li>Promjenjen je opis knjige.</li>';
        }

        // Image main changed
        if ($this->old['image'] != $this->new['image']) {
            $this->changed .= '<li>Promjenjena je glavna slika knjige.</li>';
        }
        if (count($this->old['images']) != count($this->new['images'])) {
            $this->changed .= '<li>Promjenjene su dodatne slika knjige.</li>';
        } else {
            $changed = false;
            for ($i = 0; $i < count($this->old['images']); $i++) {
                if ($this->old['images'][$i]['image'] != $this->new['images'][$i]['image']) {
                    $changed = true;
                }
            }

            if ($changed) {
                $this->changed .= '<li>Promjenjene su dodatne slika knjige.</li>';
            }
        }

        // Price changed
        if ($this->old['price'] != $this->new['price']) {
            $this->changed .= '<li>Promjenjena cijena: <b>' . number_format($this->old['price'], 2, ',', '.') . '</b> u <b>' . number_format($this->new['price'], 2, ',', '.') . '</b></li>';
        }

        // Quantity changed
        if ($this->old['quantity'] != $this->new['quantity']) {
            $this->changed .= '<li>Promjenjena količina: <b>' . $this->old['quantity'] . '</b> u <b>' . $this->new['quantity'] . '</b></li>';
        }

        // Tax changed
        if ($this->old['tax_id'] != $this->new['tax_id']) {
            $old = Settings::get('tax', 'list')->where('id', $this->old['tax_id'])->first();
            $new = Settings::get('tax', 'list')->where('id', $this->new['tax_id'])->first();

            $this->changed .= '<li>Promjenjen porez: <b>' . (isset($old->title) ? $old->title : '(Nepoznat)') . '</b> u <b>' . (isset($new->title) ? $new->title : '(Nepoznat)') . '</b></li>';
        }

        // Meta data changed
        if ($this->old['meta_title'] != $this->new['meta_title'] || $this->old['meta_description'] != $this->new['meta_description']) {
            $this->changed .= '<li>Promjenjeni meta podaci knjige.</li>';
        }

        // pages changed
        if ($this->old['pages'] != $this->new['pages']) {
            $this->changed .= '<li>Promjenjen broj stranica: <b>' . $this->old['pages'] . '</b> u <b>' . $this->new['pages'] . '</b></li>';
        }

        // dimensions changed
        if ($this->old['dimensions'] != $this->new['dimensions']) {
            $this->changed .= '<li>Promjenjene dimenzije: <b>' . $this->old['dimensions'] . '</b> u <b>' . $this->new['dimensions'] . '</b></li>';
        }

        // origin changed
        if ($this->old['origin'] != $this->new['origin']) {
            $this->changed .= '<li>Promijenjeni jezici: <b>' . $this->old['origin'] . '</b> u <b>' . $this->new['origin'] . '</b></li>';
        }

        // letter changed
        if ($this->old['letter'] != $this->new['letter']) {
            $this->changed .= '<li>Promjenjeno pismo: <b>' . $this->old['letter'] . '</b> u <b>' . $this->new['letter'] . '</b></li>';
        }

        // condition changed
        if ($this->old['condition'] != $this->new['condition']) {
            $this->changed .= '<li>Promjenjeno stanje: <b>' . $this->old['condition'] . '</b> u <b>' . $this->new['condition'] . '</b></li>';
        }

        // binding changed
        if ($this->old['binding'] != $this->new['binding']) {
            $this->changed .= '<li>Promjenjen uvez: <b>' . $this->old['binding'] . '</b> u <b>' . $this->new['binding'] . '</b></li>';
        }

        // Customer-visible product note changed
        if (($this->old['note'] ?? null) != ($this->new['note'] ?? null)) {
            $this->changed .= '<li>Promijenjena je napomena o primjerku.</li>';
        }

        // year changed
        if ($this->old['year'] != $this->new['year']) {
            $this->changed .= '<li>Promjenjena godina izdavanja: <b>' . $this->old['year'] . '</b> u <b>' . $this->new['year'] . '</b></li>';
        }

        // status changed
        if ($this->old['status'] != $this->new['status']) {
            $this->changed .= '<li>Promjenjena status vidljivosti: <b>' . ($this->new['status'] ? 'Aktiviran' : 'Deaktiviran') . '</b></li>';
        }

        // category changed
        if (isset($this->old['category']['id']) && isset($this->new['category']['id'])) {
            if ($this->old['category']['id'] != $this->new['category']['id']) {
                $this->changed .= '<li>Promjenjena kategorija: <b>' . $this->relationTitle($this->old, 'category') . '</b> u <b>' . $this->relationTitle($this->new, 'category') . '</b></li>';
            }
        }
        if (isset($this->old['subcategory']['id']) || isset($this->new['subcategory']['id'])) {
            if ((isset($this->old['subcategory']['id']) && isset($this->new['subcategory']['id'])) && $this->old['subcategory']['id'] != $this->new['subcategory']['id']) {
                $this->changed .= '<li>Promjenjena podkategorija: <b>' . $this->relationTitle($this->old, 'subcategory') . '</b> u <b>' . $this->relationTitle($this->new, 'subcategory') . '</b></li>';
            } elseif (isset($this->old['subcategory']['id']) && ! isset($this->new['subcategory']['id'])) {
                $this->changed .= '<li>Iz podkategorije: <b>' . $this->relationTitle($this->old, 'subcategory') . '</b> stavljeno u kategoriju <b>' . $this->relationTitle($this->new, 'category') . '</b></li>';
            } elseif ( ! isset($this->old['subcategory']['id']) && isset($this->new['subcategory']['id'])) {
                $this->changed .= '<li>Iz kategorija: <b>' . $this->relationTitle($this->old, 'category') . '</b> stavljeno u podkategoriju <b>' . $this->relationTitle($this->new, 'subcategory') . '</b></li>';
            }
        }
    }


    /**
     * History snapshots created before relation titles were stored can still
     * be edited safely.
     */
    private function relationTitle(array $snapshot, string $relation): string
    {
        return (string) data_get($snapshot, $relation . '.title', '(Nepoznato)');
    }
}
