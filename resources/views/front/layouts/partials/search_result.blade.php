<div class="col-md-9 bg-white-5">
    @if ($data['products'])
        @foreach ($data['products'] as $product)
            <div class="alert alert-danger alert-dismissable" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h3 class="alert-heading font-size-h4 my-2">{{ $product->card_name }}</h3>
                <p class="mb-0">{{ $product->price }}</p>
            </div>
        @endforeach
    @endif
    ...Test view...
</div>
