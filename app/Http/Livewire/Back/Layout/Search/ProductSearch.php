<?php

namespace App\Http\Livewire\Back\Layout\Search;

use App\Models\Back\Catalog\Product\Product;
use Illuminate\Http\Request;
use Livewire\Component;

class ProductSearch extends Component
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
    public $product_id = 0;

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
        'name' => ''
    ];


    /**
     *
     */
    public function mount()
    {
        if ($this->product_id) {
            $product = Product::find($this->product_id);

            if ($product) {
                $this->search = $product->name;
                $this->product_id = $product->id;
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
            $request = new Request(['search' => $this->search]);
            $this->search_results = (new Product())->filter($request)
                                                  ->limit(10)
                                                  ->get();
        }
    }


    /**
     * @param $id
     */
    public function addProduct($id)
    {
        $product = (new Product())->where('id', $id)->first();

        $this->search_results = [];
        $this->search         = $product->name;
        $this->product_id     = $product->id;

        if ($this->list) {
            return $this->emit('productSelect', ['product' => $product->toArray()]);
        }
    }


    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function render()
    {
        if ($this->search == '') {
            $this->product_id = 0;

            if ($this->list) {
                $this->emit('productSelect', ['product' => ['id' => '']]);
            }
        }

        return view('livewire.back.layout.search.product-search');
    }
}
