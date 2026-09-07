<?php

namespace App\Http\Livewire\Back\Layout\Search;

use App\Helpers\Helper;
use App\Models\Back\Catalog\Publisher;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;

class PublisherSearch extends Component
{

    /**
     * @var string
     */
    public $search = '';

    /**
     * @var array
     */
    public $search_results = [];

    /**
     * @var int
     */
    public $publisher_id = 0;

    /**
     * @var bool
     */
    public $show_add_window = false;

    /**
     * @var null|bool
     */
    public $list = null;

    /**
     * @var array
     */
    public $new = [
        'title' => ''
    ];


    /**
     *
     */
    public function mount()
    {
        if ($this->publisher_id) {
            $publisher = Publisher::find($this->publisher_id);

            if ($publisher) {
                $this->search = $publisher->title;
            }
        }
    }


    /**
     *
     */
    public function viewAddWindow()
    {
        $this->show_add_window = ! $this->show_add_window;
    }


    /**
     *
     */
    public function updatingSearch($value)
    {
        $this->search         = $value;
        $this->search_results = [];

        if ($this->search != '') {
            $this->search_results = (new Publisher())->where('title', 'LIKE', '' . $this->search . '%')
                                                  ->limit(10)
                                                  ->get()->toArray();
        }
    }


    /**
     * @param $id
     */
    public function addPublisher($id)
    {
        $publisher = (new Publisher())->where('id', $id)->first();

        $this->search_results = [];
        $this->search         = $publisher->title;
        $this->publisher_id     = $publisher->id;

        if ($this->list) {
            return $this->emit('publisherSelect', ['publisher' => $publisher->toArray()]);
        }
    }


    /**
     *
     */
    public function makeNewPublisher()
    {
        $title = Publisher::normalizeTitle($this->new['title'] ?? '');

        if ($title === '') {
            return $this->emit('error_alert', ['message' => 'Molimo vas da popunite sve podatke!']);
        }

        $existing = Publisher::findByEquivalentTitle($title);

        if ($existing) {
            $this->selectPublisher($existing);

            return $this->emit('success_alert', ['message' => 'Izdavač već postoji i odabran je postojeći zapis.']);
        }

        $slug = Str::slug($title);

        $id = Publisher::insertGetId([
            'letter'           => Helper::resolveFirstLetter($title),
            'title'            => $title,
            'description'      => '',
            'meta_title'       => $title,
            'meta_description' => '',
            'lang'             => 'hr',
            'sort_order'       => 0,
            'status'           => 1,
            'slug'             => $slug,
            'url'              => config('settings.publisher_path') . '/' . $slug,
            'created_at'       => Carbon::now(),
            'updated_at'       => Carbon::now()
        ]);

        if ($id) {
            $this->selectPublisher(Publisher::find($id));

            return $this->emit('success_alert', ['message' => 'Izdavač je uspješno dodan..!']);
        }

        return $this->emit('error_alert');
    }


    private function selectPublisher(Publisher $publisher): void
    {
        $this->show_add_window = false;
        $this->search_results = [];
        $this->new['title'] = '';
        $this->publisher_id = $publisher->id;
        $this->search = $publisher->title;
    }


    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function render()
    {
        if ($this->search == '') {
            $this->publisher_id = 0;

            if ($this->list) {
                $this->emit('publisherSelect', ['publisher' => ['id' => '']]);
            }
        }

        return view('livewire.back.layout.search.publisher-search');
    }
}
