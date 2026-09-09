@extends('back.layouts.backend')

@push('css_before')
    <link rel="stylesheet" href="{{ asset('js/plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('js/plugins/dropzone/min/dropzone.min.css') }}">
    <link rel="stylesheet" href="{{ asset('js/plugins/bootstrap-datepicker/css/bootstrap-datepicker3.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/plugins/slim/slim.css') }}">

    @stack('product_css')
@endpush

@section('content')

    @php
        $selectedProductAttributes = [
            'letter' => old('letter', $data['attribute_values']['letter'] ?? null),
            'condition' => old('condition', $data['attribute_values']['condition'] ?? null),
            'binding' => old('binding', $data['attribute_values']['binding'] ?? null),
            'origin' => old('origin', $data['attribute_values']['origin'] ?? null),
        ];
    @endphp

    <div class="admin-page-hero">
        <div class="content content-full">
            <div class="admin-page-heading">
                <div>
                    <div class="admin-page-kicker"><i class="fa fa-layer-group" aria-hidden="true"></i> Katalog</div>
                    <h1 class="admin-page-title">{{ isset($product) ? 'Uredi artikl' : 'Novi artikl' }}</h1>
                    <p class="admin-page-description">{{ isset($product) ? $product->name : 'Unesite podatke, atribute, slike i SEO postavke novog artikla.' }}</p>
                </div>
                <div class="admin-toolbar-actions">
                    <a class="btn btn-alt-secondary" href="{{ route('products') }}"><i class="fa fa-arrow-left mr-1" aria-hidden="true"></i> Svi artikli</a>
                    @if(isset($product))
                        <a class="btn btn-light" href="{{ url($product->url) }}" target="_blank" rel="noopener"><i class="fa fa-eye mr-1" aria-hidden="true"></i> Pregled</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <!-- Page Content -->
    <div class="content content-full">
        @include('back.layouts.partials.session')

        <!--tabs start-->



        <!-- tabs end-->

        <form action="{{ isset($product) ? route('products.update', ['product' => $product]) : route('products.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @if (isset($product))
                {{ method_field('PATCH') }}
            @endif


            <!-- Block Tabs Default Style -->
            <div class="block block-rounded admin-product-editor">
                <ul class="nav nav-tabs nav-tabs-block admin-editor-tabs" data-toggle="tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" href="#osnovno"><i class="si si-settings"></i> Info</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#atributi"><i class="si si-settings"></i> Atributi</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#slike"><i class="si si-picture"></i> Slike</a>
                    </li>
                    <li class="nav-item ">
                        <a class="nav-link" href="#seo">
                            <i class="si si-link"></i> SEO
                        </a>
                    </li>
                </ul>
                <div class="block-content tab-content">
                    <div class="tab-pane active" id="osnovno" role="tabpanel">
                        <div class="block admin-editor-section">
                            <div class="block-header block-header-default">
                                <div>
                                    <h2 class="block-title mb-1">Osnovni podaci</h2>
                                    <p class="text-muted mb-0 font-size-sm">Naziv, cijena, stanje zalihe i prodajni opis.</p>
                                </div>
                                <div class="block-options">
                                    <div class="dropdown">
                                        <div class="d-none custom-control custom-switch custom-control-info block-options-item ml-4">
                                            <input type="checkbox" class="custom-control-input" id="product-gift-switch" name="gift"{{ (isset($product->gift) and $product->gift) ? 'checked' : '' }}>
                                            <label class="custom-control-label pt-1" for="product-gift-switch">Poklon Bon</label>
                                        </div>

                                        <div class=" d-none custom-control custom-switch custom-control-info block-options-item ml-4">
                                            <input type="checkbox" class="custom-control-input" id="product-decrease-switch" name="decrease"{{ (isset($product->decrease) and $product->decrease) ? '' : 'checked' }}>
                                            <label class="custom-control-label pt-1" for="product-decrease-switch">Neograničena Količina</label>
                                        </div>

                                        <div class="custom-control custom-switch custom-control-success block-options-item ml-4">
                                            <input type="checkbox" class="custom-control-input" id="product-switch" name="status"{{ (isset($product->status) and $product->status) ? 'checked' : '' }}>
                                            <label class="custom-control-label pt-1" for="product-switch">Aktiviraj</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="block-content">
                                <div class="row justify-content-center push">
                                    <div class="col-md-12">
                                        <div class="form-group row items-push mb-3">
                                            <div class="col-md-9">
                                                <label for="dm-post-edit-title">Naziv <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="name-input" name="name" placeholder="Upišite naziv artikla" value="{{ isset($product) ? $product->name : old('name') }}" onkeyup="SetSEOPreview()">
                                                @error('name')
                                                <span class="text-danger font-italic">Naziv je potreban...</span>
                                                @enderror
                                            </div>

                                            <div class="col-md-3">
                                                <label for="isbn-input">EAN</label>
                                                <input type="text" class="form-control" id="isbn-input" name="isbn" placeholder="Upišite EAN" value="{{ isset($product) ? $product->isbn : old('isbn') }}">
                                            </div>
                                        </div>
                                        <div class="form-group row items-push mb-3">

                                            <div class="col-md-3">
                                                <label for="price-input">Cijena <span class="text-danger">*</span> <span class="small text-gray">(S PDV-om)</span></label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="price-input" name="price" placeholder="00.00" value="{{ isset($product) ? $product->price : old('price') }}">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text">€</span>
                                                    </div>
                                                </div>
                                                @error('price')
                                                <span class="text-danger font-italic">Cijena je potrebna...</span>
                                                @enderror
                                            </div>

                                            <div class="col-md-3">
                                                <label for="quantity-input">Količina <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="quantity-input" name="quantity" placeholder="Upišite količinu artikla" value="{{ isset($product) ? $product->quantity : ( ! isset($product) ? 1 : old('quantity')) }}">
                                                @error('quantity')
                                                <span class="text-danger font-italic">Količina je potrebna...</span>
                                                @enderror
                                            </div>

                                            <div class="col-md-3">
                                                <label for="sku-input">Šifra <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="sku-input" name="sku" placeholder="Upišite šifru artikla" value="{{ isset($product) ? $product->sku : old('sku') }}">
                                                @error('sku')
                                                <span class="text-danger font-italic">Šifra je potrebna...</span>
                                                @enderror
                                                @error('sku_dupl')
                                                <span class="text-danger small font-italic">{{ $message }}</span>
                                                @enderror
                                            </div>

                                            <div class="col-md-3">
                                                <label for="polica-input">Šifra police </label>
                                                <input type="text" class="form-control" id="polica-input" name="polica" placeholder="Upišite šifru police" value="{{ isset($product) ? $product->polica : old('polica') }}" >
                                            </div>



                                        </div>

                                        <div class="form-group row items-push mb-3">
                                            <div class="col-md-3">
                                                <label for="special-input">Akcija</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="special-input" name="special" placeholder="00.00" value="{{ isset($product) ? $product->special : old('special') }}">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text">€</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="special-from-input">Akcija vrijedi</label>
                                                <div class="input-daterange input-group" data-date-format="mm/dd/yyyy" data-week-start="1" data-autoclose="true" data-today-highlight="true">
                                                    <input type="text" class="form-control" id="special-from-input" name="special_from" placeholder="od" value="{{ (isset($product->special_from) && $product->special_from != '0000-00-00 00:00:00') ? \Carbon\Carbon::make($product->special_from)->format('d.m.Y') : '' }}" data-week-start="1" data-autoclose="true" data-today-highlight="true">
                                                    <div class="input-group-prepend input-group-append">
                                                        <span class="input-group-text font-w600"><i class="fa fa-fw fa-arrow-right"></i></span>
                                                    </div>
                                                    <input type="text" class="form-control" id="special-to-input" name="special_to" placeholder="do" value="{{ (isset($product->special_to) && $product->special_to != '0000-00-00 00:00:00') ? \Carbon\Carbon::make($product->special_to)->format('d.m.Y') : '' }}" data-week-start="1" data-autoclose="true" data-today-highlight="true">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="tax-select">Porez</label>
                                                <select class="js-select2 form-control" id="tax-select" name="tax_id" style="width: 100%;" data-placeholder="Odaberite porez...">
                                                    <option></option>
                                                    @foreach ($data['taxes'] as $tax)
                                                        <option value="{{ $tax->id }}" {{ ((isset($product)) and ($tax->id == $product->tax_id)) ? 'selected' : (( ! isset($product) and ($tax->id == 1)) ? 'selected' : '') }}>{{ $tax->title }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <!-- CKEditor 5 Classic (js-ckeditor5-classic in Helpers.ckeditor5()) -->
                                        <!-- For more info and examples you can check out http://ckeditor.com -->
                                        <div class="form-group row mb-4">
                                            <div class="col-md-12">
                                                <label for="description-editor">Opis</label>
                                                <textarea id="description-editor" name="description">{!! isset($product) ? $product->description : old('description') !!}</textarea>
                                            </div>
                                        </div>



                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="atributi" role="tabpanel">
                        <div class="block admin-editor-section">

                            <div class="block-content">
                                <div class="row justify-content-center push">
                                    <div class="col-md-12">


                                        <div class="form-group row items-push mb-4">

                                            <div class="col-md-12">
                                                <label for="categories">Odaberi grupu @include('back.layouts.partials.required-star')</label>
                                                <select class="form-control" id="grupa-select" name="group" style="width: 100%;" >
                                                    <option></option><!-- Required for data-placeholder attribute to work with Select2 plugin -->
                                                    @foreach ($data['groups'] as $group)
                                                        <option value="{{ $group->slug }}" class="font-weight-bold small" {{ ((isset($product)) and ($group->slug == $product->group)) ? 'selected' : '' }}>{{ $group->title }}</option>
                                                    @endforeach
                                                </select>
                                                @error('group')
                                                <span class="text-danger font-italic">Grupa je obavezna je potrebna...</span>
                                                @enderror
                                            </div>


                                            <div class="col-md-12">
                                                <label for="categories">Odaberi kategorije @include('back.layouts.partials.required-star')</label>
                                                <select class="form-control" id="category-select" name="category[]" style="width: 100%;" multiple>
                                                    <option></option><!-- Required for data-placeholder attribute to work with Select2 plugin -->
                                                    @foreach ($data['categories'] as $group => $cats)
                                                        @foreach ($cats as $id => $category)
                                                            <option value="{{ $id }}" class="font-weight-bold small" {{ ((isset($product)) and (in_array($id, $product->categories()->pluck('id')->toArray()))) ? 'selected' : '' }}>{{ $category['title'] }}</option>
                                                            @if ( ! empty($category['subs']))
                                                                @foreach ($category['subs'] as $sub_id => $subcategory)
                                                                    <option value="{{ $sub_id }}" class="pl-3 text-sm" {{ ((isset($product) && $product->subcategory()) and ($sub_id == $product->subcategory()->id)) ? 'selected' : '' }}>{{ $category['title'] . ' >> ' . $subcategory['title'] }}</option>
                                                                @endforeach
                                                            @endif
                                                        @endforeach
                                                    @endforeach
                                                </select>
                                                @error('category')
                                                <span class="text-danger font-italic">Kategorija je potrebna...</span>
                                                @enderror
                                            </div>
                                            <div class="col-md-6">
                                                <label for="dm-post-edit-slug">Autor</label>
                                                @livewire('back.layout.search.author-search', ['author_id' => isset($product) ? $product->author_id : 0])
                                            </div>
                                            <div class="col-md-6">
                                                <label for="dm-post-edit-slug">Izdavač</label>
                                                @livewire('back.layout.search.publisher-search', ['publisher_id' => isset($product) ? $product->publisher_id : 0])
                                            </div>
                                        </div>

                                        <div class="form-group row items-push mb-4">
                                            <div class="col-md-6 col-xl-3">
                                                <label for="letter-select">Pismo</label>
                                                <select class="js-select2 form-control" id="letter-select" name="letter" style="width: 100%;" data-placeholder="Odaberite pismo">
                                                    <option></option>
                                                    @if ($data['letters'])
                                                        @foreach ($data['letters'] as $letter)
                                                            <option value="{{ $letter }}" {{ $letter === $selectedProductAttributes['letter'] ? 'selected' : '' }}>
                                                                {{ $letter }}{{ ($data['legacy_attribute_values']['letter'] ?? null) === $letter ? ' (postojeća vrijednost)' : '' }}
                                                            </option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                                @error('letter')
                                                <span class="text-danger font-italic">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div class="col-md-6 col-xl-3">
                                                <label for="condition-select">Stanje</label>
                                                <select class="js-select2 form-control" id="condition-select" name="condition" style="width: 100%;" data-placeholder="Odaberite stanje">
                                                    <option></option>
                                                    @if ($data['conditions'])
                                                        @foreach ($data['conditions'] as $condition)
                                                            <option value="{{ $condition }}" {{ $condition === $selectedProductAttributes['condition'] ? 'selected' : '' }}>
                                                                {{ $condition }}{{ ($data['legacy_attribute_values']['condition'] ?? null) === $condition ? ' (postojeća vrijednost)' : '' }}
                                                            </option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                                @error('condition')
                                                <span class="text-danger font-italic">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div class="col-md-6 col-xl-3">
                                                <label for="binding-select">Uvez</label>
                                                <select class="js-select2 form-control" id="binding-select" name="binding" style="width: 100%;" data-placeholder="Odaberite uvez">
                                                    <option></option>
                                                    @if ($data['bindings'])
                                                        @foreach ($data['bindings'] as $binding)
                                                            <option value="{{ $binding }}" {{ $binding === $selectedProductAttributes['binding'] ? 'selected' : '' }}>
                                                                {{ $binding }}{{ ($data['legacy_attribute_values']['binding'] ?? null) === $binding ? ' (postojeća vrijednost)' : '' }}
                                                            </option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                                @error('binding')
                                                <span class="text-danger font-italic">{{ $message }}</span>
                                                @enderror
                                            </div>

                                            <div class="col-md-6 col-xl-3">
                                                <label for="origin-select">Jezik</label>
                                                <select class="js-select2 form-control" id="origin-select" name="origin" style="width: 100%;" data-placeholder="Odaberite jezik">
                                                    <option></option>
                                                    @if ($data['origins'])
                                                        @foreach ($data['origins'] as $origin)
                                                            <option value="{{ $origin }}" {{ $origin === $selectedProductAttributes['origin'] ? 'selected' : '' }}>
                                                                {{ $origin }}{{ ($data['legacy_attribute_values']['origin'] ?? null) === $origin ? ' (postojeća vrijednost)' : '' }}
                                                            </option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                                @error('origin')
                                                <span class="text-danger font-italic">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="form-group row items-push mb-3">

                                            <div class="col-md-4 ">
                                                <label for="origin-input">Godina izdavanja</label>
                                                <input type="text" class="form-control" id="year-input" name="year" placeholder="Upišite godinu izdavanja" value="{{ isset($product) ? $product->year : old('year') }}">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="pages-input">Broj stranica</label>
                                                <input type="text" class="form-control" id="pages-input" name="pages" placeholder="Upišite broj stranica" value="{{ isset($product) ? $product->pages : old('pages') }}">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="dimensions-input">Dimenzije</label>
                                                <input type="text" class="form-control" id="dimensions-input" name="dimensions" placeholder="Upišite dimenzije" value="{{ isset($product) ? $product->dimensions : old('dimensions') }}">
                                            </div>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="note-input">Napomena</label>
                                            <textarea class="form-control" id="note-input" name="note" rows="4" maxlength="2000" placeholder="Npr. posveta na prvoj stranici, oštećenje korica ili druga važna napomena o primjerku">{{ old('note', isset($product) ? $product->note : '') }}</textarea>
                                            <small class="form-text text-muted">Vidljiva je kupcu uz dodatne informacije o artiklu.</small>
                                            @error('note')
                                            <span class="d-block text-danger font-italic">{{ $message }}</span>
                                            @enderror
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="slike" role="tabpanel">
                        <div class="block admin-editor-section">
                            <div class="block-header block-header-default">
                                <h3 class="block-title">Slike</h3>
                            </div>
                            <div class="block-content block-content-full">
                                <div class="row justify-content-center">
                                    <div class="col-md-12">
                                        <!-- Dropzone (functionality is auto initialized by the plugin itself in js/plugins/dropzone/dropzone.min.js) -->
                                        <!-- For more info and examples you can check out http://www.dropzonejs.com/#usage -->
                                        <!--                            <div class="dropzone">
                                                                        <div class="dz-message" data-dz-message><span>Klikni ovdje ili dovuci slike za uplad</span></div>
                                                                    </div>-->
                                        @include('back.catalog.product.edit-photos')
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane" id="seo" role="tabpanel">
                        <div class="block admin-editor-section">
                            <div class="block-header block-header-default">
                                <h3 class="block-title">Meta Data - SEO</h3>
                            </div>
                            <div class="block-content">
                                <div class="row justify-content-center">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="meta-title-input">Meta naslov</label>
                                            <input type="text" class="js-maxlength form-control" id="meta-title-input" name="meta_title" value="{{ isset($product) ? $product->meta_title : old('meta_title') }}" maxlength="70" data-always-show="true" data-placement="top">
                                            <small class="form-text text-muted">
                                                70 znakova max
                                            </small>
                                        </div>
                                        <div class="form-group">
                                            <label for="meta-description-input">Meta opis</label>
                                            <textarea class="js-maxlength form-control" id="meta-description-input" name="meta_description" rows="4" maxlength="160" data-always-show="true" data-placement="top">{{ isset($product) ? $product->meta_description : old('meta_description') }}</textarea>
                                            <small class="form-text text-muted">
                                                160 znakova max
                                            </small>
                                        </div>
                                        <div class="form-group">
                                            <label for="slug-input">SEO link (url)</label>
                                            <input type="text" class="form-control" id="slug-input" value="{{ isset($product) ? $product->slug : old('slug') }}" disabled>
                                            <input type="hidden" name="slug" value="{{ isset($product) ? $product->slug : old('slug') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <!-- END Block Tabs Default Style -->


            <div class="admin-form-actions product-editor-actions">
                <span class="product-save-note"><i class="fa fa-info-circle mr-1" aria-hidden="true"></i> Promjene se primjenjuju tek nakon spremanja.</span>
                @if (isset($product))
                    <a href="{{ route('products.destroy', ['product' => $product]) }}" class="btn btn-alt-danger product-delete-action js-tooltip-enabled" data-toggle="tooltip" title="" data-original-title="Obriši artikl" onclick="event.preventDefault(); if (confirm('Sigurno želite trajno obrisati ovaj artikl?')) document.getElementById('delete-product-form{{ $product->id }}').submit();">
                        <i class="fa fa-trash-alt mr-1" aria-hidden="true"></i> Obriši
                    </a>
                @endif
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save mr-1" aria-hidden="true"></i> Spremi artikl
                </button>
            </div>
        </form>

        @if (isset($product))
            <form id="delete-product-form{{ $product->id }}" action="{{ route('products.destroy', ['product' => $product]) }}" method="POST" style="display: none;">
                @csrf
                {{ method_field('DELETE') }}
            </form>
        @endif
    </div>
@endsection

@push('js_after')
    <!-- Page JS Plugins -->
    <script src="{{ asset('js/plugins/select2/js/select2.full.min.js') }}"></script>
    <script src="{{ asset('js/plugins/ckeditor5-classic/build/ckeditor.js') }}"></script>
    <script src="{{ asset('js/plugins/dropzone/min/dropzone.min.js') }}"></script>
    <script src="{{ asset('js/plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js') }}"></script>
    <script src="{{ asset('js/plugins/jquery.maskedinput/jquery.maskedinput.min.js') }}"></script>
    <script src="{{ asset('js/plugins/slim/slim.kickstart.js') }}"></script>

    <!-- Page JS Helpers (CKEditor 5 plugins) -->
    <script>jQuery(function(){Dashmix.helpers(['datepicker']);});</script>

    <script>
        $(() => {
            ClassicEditor
            .create(document.querySelector('#description-editor'))
            .then(() => {})
            .catch(error => {
                console.error(error);
            });

            $('#category-select').select2({});
            $('#grupa-select').select2({
                placeholder: 'Odaberite...',
                minimumResultsForSearch: Infinity
            });
            $('#tax-select').select2({});
            $('#action-select').select2({
                placeholder: 'Odaberite...',
                minimumResultsForSearch: Infinity
            });
            $('#author-select').select2({
                tags: true
            });
            $('#publisher-select').select2({
                tags: true
            });
            $('#letter-select, #binding-select, #condition-select, #origin-select').select2({
                allowClear: true
            });
            $('#shipping_time-select').select2({
                tags: true
            });

            Livewire.on('success_alert', () => {

            });

            Livewire.on('error_alert', (e) => {

            });
        })
    </script>

    <script>
        function SetSEOPreview() {
            let title = $('#name-input').val();
            $('#slug-input').val(slugify(title));
        }
    </script>

    @stack('product_scripts')

@endpush
