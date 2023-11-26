@push('product_css')
    <style>
        .fileContainer {
            overflow: hidden;
            position: relative;
        }

        .fileContainer [type=file] {
            cursor: inherit;
            display: block;
            font-size: 999px;
            filter: alpha(opacity=0);
            min-height: 34px;
            min-width: 100%;
            opacity: 0;
            position: absolute;
            right: 0;
            text-align: right;
            top: 0;
        }

        .fileContainer {
            background: #E3E3E3;
            float: left;
            padding: .5em 1.5rem;
            height: 34px;
        }

        .fileContainer [type=file] {
            cursor: pointer;
        }

        img.preview {
            width: 200px;
            background-color: white;
            border: 1px solid #DDD;
            padding: 5px;
        }
    </style>
@endpush

<div>
    <div class="row">
        <div class="col-12">
            <div class="file-drop-area">
                <label for="files" style="display: block;padding: 1rem 2rem;border: 1px solid #CCCCCC;background-color: #eee;text-align: center;cursor: pointer;">Odaberite fotografiju proizvoda... Ili više njih...</label>
                <input name="files[][image]" id="files" type="file" multiple>
            </div>
        </div>
    </div>

    <div class="row items-push" id="sortable">
        @if (isset($product))
            @if (! empty($product->image))
                <div class="col-sm-12 animated fadeIn mb-0 p-3 ribbon ribbon-left ribbon-bookmark ribbon-crystal" id="{{ 'image_0' }}">
                    <div class="row form-group mt-2">
                        <div class="col-sm-3">
                            <div class="options-container fx-item-zoom-in fx-overlay-zoom-out">
                                <div class="ribbon-box" style="background-color: #c3c3c3">
                                    <i class="fa fa-check"></i> Glavna Slika
                                </div>
                                <div class="slim"
                                     {{--data-service="{{ route('images.upload') }}"--}}
                                     data-ratio="free"
                                     {{--                             data-size="600,800"--}}
                                     data-max-file-size="2"
                                     data-meta-type="products"
                                     data-meta-type_id="{{ $product->id }}"
                                     data-will-remove="removeImage"
                                        {{--data-will-request="handleXHRRequest"--}}>
                                    <img src="{{ asset($product->image) }}" alt="{{ 'image_' . $product->id }}"/>
                                    <input type="file" name="slim[0][image]"/>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-9">
                            <div class="row mb-2">
                                <label class="col-sm-3 text-right font-size-sm pt-2">Naziv fotografije</label>
                                <div class="col-sm-9">
                                    <input type="text" id="max" class="form-control js-tooltip-enabled" name="slim[0][title]" value="{{ $product->imageName() }}" data-toggle="tooltip" data-placement="top" title="Image Title" placeholder="Naziv fotografije">
                                </div>
                            </div>
                            <div class="row mb-4">
                                <label class="col-sm-3 text-right font-size-sm pt-2">Alt. tekst</label>
                                <div class="col-sm-9 font-size-sm">
<!--                                    Alternativni tekst glavne fotografije je jednak nazivu knjige + autor.-->
                                    <input type="text" id="max" class="form-control js-tooltip-enabled" name="slim[0][alt]" value="{{ $product->image_alt }}" data-toggle="tooltip" data-placement="top" title="Image Alt Text" placeholder="Alternativni Naziv fotografije">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 text-right">
                                    <label class="css-control css-control-primary css-radio">
                                        <input type="radio" class="css-control-input" name="slim[default]" checked>
                                        Glavna fotografija<span class="css-control-indicator"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="col-sm-12">
                <div class="row items-push" id="new-images">
                    @if (! empty($data['images']))
                        @foreach($data['images'] as $image)
                            <div class="col-sm-12 animated fadeIn mb-0 p-3 ribbon ribbon-left ribbon-bookmark ribbon-crystal" id="{{ 'image_id_' . $image['id'] }}">
                                <div class="row form-group mt-2">
                                    <div class="col-md-2 col-sm-3">
                                        <div class="options-container fx-item-zoom-in fx-overlay-zoom-out">
                                            <div class="slim"
                                                 {{--data-service="{{ route('images.ajax.upload') }}"--}}
                                                 data-ratio="free"
                                                 {{--                                         data-size="600,800"--}}
                                                 data-max-file-size="2"
                                                 data-meta-type="products"
                                                 data-meta-type_id="{{ $product->id }}"
                                                 data-meta-image_id="{{ $image['id'] }}"
                                                 data-will-remove="removeImage"
                                            >
                                                <img src="{{ asset($image['image']) }}" alt="{{ 'image_' . $image['id'] }}"/>
                                                <input type="file" name="slim[{{ $image['id'] }}][image]"/>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-10 col-sm-9">
                                        <div class="row mb-2">
                                            <label class="col-sm-3 text-right font-size-sm pt-2">Naziv fotografije</label>
                                            <div class="col-sm-9">
                                                <input type="text" class="form-control js-tooltip-enabled" name="slim[{{ $image['id'] }}][title]" value="{{ $image['title'] }}" data-toggle="tooltip" data-placement="top" title="Image Title" placeholder="Naziv fotografije">
                                            </div>
                                        </div>
                                        <div class="row mb-4">
                                            <label class="col-sm-3 text-right font-size-sm pt-2">Alt. tekst</label>
                                            <div class="col-sm-9">
                                                <input type="text" class="form-control js-tooltip-enabled" name="slim[{{ $image['id'] }}][alt]" value="{{ $image['alt'] }}" data-toggle="tooltip" data-placement="top" title="Image Alt Text" placeholder="Alternativni tekst fotografije">
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label class="col-sm-9 text-right font-size-sm pt-2" >Redosljed</label>
                                            <div class="col-sm-3">
                                                <input type="text" class="form-control js-tooltip-enabled" name="slim[{{ $image['id'] }}][sort_order]" value="{{ $image['sort_order'] }}" data-toggle="tooltip" data-placement="top" title="Sort Order">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12 text-right mb-2">
                                                <div class="custom-control custom-radio mb-1">
                                                    <input type="radio" class="custom-control-input" id="radio-default" name="slim[default]" value="{{ $image['id'] }}">
                                                    <label class="custom-control-label" for="radio-default">Glavna fotografija</label>
                                                </div>
                                            </div>
                                            <div class="col-md-12 text-right">
                                                <div class="custom-control custom-checkbox custom-checkbox-square custom-control-success mb-1">
                                                    <input type="checkbox" class="custom-control-input" id="check-published[{{ $image['id'] }}]" name="slim[{{ $image['id'] }}][published]" @if($image['published']) checked @endif>
                                                    <label class="custom-control-label" for="check-published[{{ $image['id'] }}]">Vidljivost foto.</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <input type="hidden" name="images_order" id="images-order">
                    @endif
                </div>
            </div>
        @else
            <div class="row items-push" id="new-images"></div>
        @endif
    </div>

</div>

@push('product_scripts')

    <script>
        // let el = $('#max');
        //
        // el.maxlength({
        //     alwaysShow: true,
        //     threshold: el.data('threshold') || 10,
        //     warningClass: el.data('warning-class') || 'badge badge-warning',
        //     limitReachedClass: el.data('limit-reached-class') || 'badge badge-danger',
        //     placement: el.data('placement') || 'bottom',
        //     preText: el.data('pre-text') || '',
        //     separator: el.data('separator') || '/',
        //     postText: el.data('post-text') || ''
        // });
    </script>

    <script>
        //
        let blocks = "{{ (isset($product) && isset($product->images)) ? count($product->images) : 0 }}";
        let created_id = 0;
        // get a reference to the file drop area and the file input
        var fileDropArea = document.querySelector('.file-drop-area');
        var fileInput = fileDropArea.querySelector('input');
        var fileInputName = fileInput.name;

        // listen to events for dragging and dropping
        fileDropArea.addEventListener('dragover', handleDragOver);
        fileDropArea.addEventListener('drop', handleDrop);
        fileInput.addEventListener('change', handleFileSelect);

        function handleDragOver(e) {
            e.preventDefault();
        }
        function handleDrop(e) {
            e.preventDefault();
            handleFileItems(e.dataTransfer.items || e.dataTransfer.files);
        }
        function handleFileSelect(e) {
            handleFileItems(e.target.files);
        }

        // loops over a list of items
        function handleFileItems(items) {
            let l = items.length;
            for (let i=0; i<l; i++) {
                handleItem(items[i]);
            }
        }

        function handleItem(item) {
            // get file from item
            let file = item;
            if (item.getAsFile && item.kind == 'file') {
                file = item.getAsFile();
            }

            handleFile(file);
        }

        // now we're sure each item is a file
        function handleFile(file) {
            createCropper(file);
        }

        // create an Image Cropper for each passed file
        function createCropper(file) {
            // create container element for cropper
            let holder = document.getElementById('new-images');

            let col = document.createElement('div');
            col.className = 'col-lg-3 col-md-4 animated fadeIn mb-5 p-3 ribbon ribbon-left ribbon-bookmark ribbon-crystal';

            let cropper = document.createElement('div');

            // insert this element after the file drop area
            col.insertAdjacentElement('afterbegin', cropper);
            col.insertAdjacentHTML('beforeend', '<div class="row form-group mt-2">\n' +
                '                                    <div class="col-sm-4" style="padding-right: 0;">\n' +
                '                                        <input type="text" class="form-control js-tooltip-enabled" name="files[' + created_id + '][sort_order]" value="' + blocks + '" data-toggle="tooltip" data-placement="top" title="Sort Order">\n' +
                '                                    </div>\n' +
                '                                    <div class="col-sm-8 text-right">\n' +
                '                                        <label class="css-control css-control-primary css-radio mt-2">\n' +
                '                                            <input type="radio" class="css-control-input" name="files[default]" value="image/' + file.name + '">\n' +
                '                                            <span class="mr-2">Default</span> <span class="css-control-indicator"></span>\n' +
                '                                        </label>\n' +
                '                                    </div>\n' +
                '                                </div>');

            holder.insertAdjacentElement('beforeend', col);

            // create a Slim Cropper
            Slim.create(cropper, {
                ratio: 'free',
                //size: '600,800',
                maxFileSize: '2',
                service: false,
                meta: {
                    type: 'products',
                    type_id: "{{ isset($product) ? $product->id : '' }}",
                    image_id: 0
                },
                defaultInputName: fileInputName,
                didInit: function() {
                    // load the file to our slim cropper
                    this.load(file);

                },
                didRemove: function(data, slim) {
                    col.parentNode.removeChild(col)
                    // destroy the slim cropper
                    this.destroy();

                }
            });

            blocks++;
            created_id++;
        }

        function handleXHRRequest(xhr) {
            xhr.setRequestHeader('X-CSRF-TOKEN', "{{ csrf_token() }}");

            console.log(fileInput)
        }

        function removeImage(data, slim) {
            if (data.meta.hasOwnProperty('image_id')) {
                axios.post("{{ route('products.destroy.image') }}", { data: data.meta.image_id })
                    .then((response) => {
                        successToast.fire({
                            text: 'Fotografija je uspješno izbrisana',
                        })

                        let elem = document.getElementById('image_id_' + data.meta.image_id);

                        elem.parentNode.removeChild(elem);
                    })
                    .catch((error) => {
                        errorToast.fire({
                            text: 'Greška u brisanju fotografije..! Molimo pokušajte ponovo.',
                        })
                    })
            } else {
                errorToast.fire({
                    text: 'Glavna slika se ne može izbrisati..!',
                })
            }

            //slim.destroy();
        }

        // hide file input, we can now upload with JavaScript
        fileInput.style.display = 'none';

        // remove file input name so it's value is
        // not posted to the server
        fileInput.removeAttribute('name');
    </script>

@endpush
