<div class="mb-0 input-group">
    <input type="search" wire:model.debounce.300ms="search" class="form-control @error('publisher_id') is-invalid @enderror" id="product-input" placeholder="Odaberi proizvod..." autocomplete="off">
    <input type="hidden" name="product_id" value="{{ $product_id }}">
    @if( ! empty($search_results))
        <div class="autocomplete pt-1" style="position:absolute; z-index:10; top:38px; background-color: #f6f6f6; border: 1px solid #d7d7d7;width:100%">
            <div id="myInputautocomplete-list" class="autocomplete-items">
                @foreach($search_results as $product)
                    <div style="cursor: pointer;border-bottom: 1px solid #d7d7d7;padding-bottom: 10px;padding-left: 10px;font-size: 16px" wire:click="addProduct('{{ $product->id }}')">
                        <small class="font-weight-lighter">Naziv: <strong>{{ $product->name }}</small>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            document.addEventListener('livewire:load', function () {

            });

            Livewire.on('success_alert', () => {

            });

            Livewire.on('error_alert', (e) => {

            });
        </script>
    @endpush

</div>
