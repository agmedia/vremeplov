<div class="block block-rounded">
    <div class="block-content bg-body-light" style="padding: 12px 20px;">
        <div class="row">
            <div class="col-md-3">
                <h3 class="block-title pt-1">Stavke</h3>
            </div>
            <div class="col-md-9">
                <div class="block-options">
                    <input type="search" wire:model.debounce.300ms="search" @if( ! $is_search_active) readonly @endif class="form-control" style="display: block;" placeholder="Traži...">
                    @if( ! empty($search_results))
                        <div class="autocomplete" >
                            <div id="myInputautocomplete-list" class="autocomplete-items">
                                @foreach($search_results as $item)
                                    <div wire:click="addItem({{ $item->id }})">
                                        @if ($group === 'reviews')
                                            {{ trim($item->fname . ' ' . $item->lname) ?: 'Anonimno' }} — {{ \Illuminate\Support\Str::limit($item->message, 65) }}
                                            @if ($item->product)<small>({{ $item->product->name }})</small>@endif
                                        @else
                                            {{ isset($item->title) ? $item->title : $item->name }} {{ isset($item->sku) ? '— ' . $item->sku : '' }}
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
    <div class="block-content">
        <!-- All Products Table -->
        <div class="table-responsive">
            <table class="table table-sm table-borderless table-striped table-vcenter">
                <thead>
                <tr>
                    <th>Naziv</th>
                    <th class="text-right" style="width: 100px;">Izbriši</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($list as $item)
                    <tr>
                        <td class="font-size-sm">
                            @if ($group === 'reviews')
                                {{ trim(($item['fname'] ?? '') . ' ' . ($item['lname'] ?? '')) ?: 'Anonimno' }} — {{ \Illuminate\Support\Str::limit($item['message'] ?? '', 75) }}
                            @else
                                {{ $item['title'] ?? ($item['name'] ?? 'Stavka #' . ($item['id'] ?? '')) }} {{ isset($item['sku']) ? '— ' . $item['sku'] : '' }}
                            @endif
                            <input type="hidden" name="{{ $inputName }}[{{ isset($item['id']) ? $item['id'] : '' }}]" value="{{ isset($item['id']) ? $item['id'] : '' }}">
                        </td>
                        <td class="text-right font-size-sm">
                            <a class="btn btn-sm btn-alt-secondary" href="javascript:void(0)" wire:click="removeItem({{ isset($item['id']) ? $item['id'] : '' }})">
                                <i class="fa fa-fw fa-times text-danger"></i>
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if ($includeGroupInput)
            <input type="hidden" value="{{ $group }}" name="group">
        @endif

    </div>
</div>
