<?php

namespace App\Http\Livewire\Back\Marketing;

use App\Models\Back\Catalog\Author;
use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Marketing\Blog;
use App\Models\Back\Marketing\Review;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class ActionGroupList extends Component
{
    use WithPagination;

    /**
     * @var string[]
     */
    protected $listeners = [
        'groupUpdated' => 'groupSelected'
    ];

    /**
     * @var string
     */
    public $search = '';

    /**
     * @var array
     */
    public $search_results = [];

    /**
     * @var bool
     */
    public $is_search_active = true;

    /**
     * @var string
     */
    public $group = '';

    /**
     * @var Collection
     */
    public $list = [];

    /** @var string */
    public $inputName = 'action_list';

    /** @var int|null */
    public $maxItems;

    /** @var string|null */
    public $productGroup;

    /** @var bool */
    public $includeGroupInput = true;

    /** @var bool */
    public $emitListState = true;


    public function mount()
    {
        if ( ! empty($this->list)) {
            $ids = $this->list;
            $this->list = [];

            foreach ($ids as $id) {
                $this->addItem(intval($id));
            }

            $this->render();
        }
    }


    /**
     * @param string $value
     */
    public function updatingSearch(string $value)
    {
        $this->search = $value;
        $this->search_results = [];

        if ($this->search != '') {
            switch ($this->group) {
                case 'product':
                    $query = Product::query()
                        ->where(function ($query) {
                            $query->where('name', 'like', '%' . $this->search . '%')
                                ->orWhere('sku', 'like', '%' . $this->search . '%');
                        });

                    if ($this->productGroup) {
                        $query->where('group', $this->productGroup);
                    }

                    $this->search_results = $query->limit(5)->get();
                    break;
                case 'category':
                case 'product_category':
                    $this->search_results = Category::where('title', 'like', '%' . $this->search . '%')->limit(5)->get();
                    break;
                case 'publisher':
                    $this->search_results = Publisher::where('title', 'like', '%' . $this->search . '%')->limit(5)->get();
                    break;
                case 'author':
                    $this->search_results = Author::where('title', 'like', '%' . $this->search . '%')->limit(5)->get();
                    break;
                case 'blog':
                    $this->search_results = Blog::where('title', 'like', '%' . $this->search . '%')->limit(5)->get();
                    break;
                case 'reviews':
                    $this->search_results = Review::query()
                        ->where('status', 1)
                        ->where(function ($query) {
                            $query->where('fname', 'like', '%' . $this->search . '%')
                                ->orWhere('lname', 'like', '%' . $this->search . '%')
                                ->orWhere('message', 'like', '%' . $this->search . '%')
                                ->orWhereHas('product', function ($product) {
                                    $product->where('name', 'like', '%' . $this->search . '%');
                                });
                        })
                        ->with('product:id,name')
                        ->latest('id')
                        ->limit(8)
                        ->get();
                    break;
            }
        }
    }


    /**
     * @param int $id
     */
    public function addItem(int $id)
    {
        $this->search = '';
        $this->search_results = [];

        if (isset($this->list[$id])) {
            return;
        }

        if ($this->maxItems && count($this->list) >= (int) $this->maxItems) {
            $this->emit('error_alert', ['message' => 'Možete odabrati najviše ' . (int) $this->maxItems . ' stavki.']);

            return;
        }

        switch ($this->group) {
            case 'product':
                $query = Product::where('id', $id);
                if ($this->productGroup) {
                    $query->where('group', $this->productGroup);
                }
                $item = $query->first();
                if ($item) {
                    $this->list[$id] = $item;
                }
                break;
            case 'category':
            case 'product_category':
                $this->list[$id] = Category::where('id', $id)->first();
                break;
            case 'publisher':
                $this->list[$id] = Publisher::where('id', $id)->first();
                break;
            case 'author':
                $this->list[$id] = Author::where('id', $id)->first();
                break;
            case 'blog':
                $this->list[$id] = Blog::where('id', $id)->first();
                break;
            case 'reviews':
                $this->list[$id] = Review::with('product:id,name')->where('id', $id)->first();
                break;
        }
    }


    /**
     * @param int $id
     */
    public function removeItem(int $id)
    {
        if (isset($this->list[$id])) {
            unset($this->list[$id]);
        }
    }


    /**
     * @param string $group
     */
    public function groupSelected(string $group)
    {
        $this->group = $group;
        $this->checkGroup();
    }


    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function render()
    {
        if ($this->emitListState) {
            if ( ! empty($this->list)) {
                $this->emit('list_full');
            } else {
                $this->emit('list_empty');
            }
        }

        $this->checkGroup();

        return view('livewire.back.marketing.action-group-list', [
            'list' => $this->list,
            'group' => $this->group
        ]);
    }


    /**
     * @return string
     */
    public function paginationView()
    {
        return 'vendor.pagination.bootstrap-livewire';
    }


    private function checkGroup()
    {
        if (in_array($this->group, ['all', 'total'])) {
            $this->is_search_active = false;
        } else {
            $this->is_search_active = true;
        }
    }
}
