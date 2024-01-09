<!-- {"title": "Banneri", "description": "Widget za bannere"} -->
<section class="container  " >
   <!-- <div class="d-flex flex-wrap justify-content-between align-items-center pt-1  pb-3 mb-3">
        <h2 class="h3 mb-0 pt-3 font-title me-3"> DODATNO U PONUDI </h2>
    </div> -->

    <div class="row  mt-1 ">
        @foreach ($data as $widget)
            <div class="col-sm-12 col-lg-{{ $widget['width'] }} mb-grid-gutter">
                <div class="d-block d-sm-flex justify-content-between align-items-center  rounded-3" style="background-image: url({{ config('settings.images_domain') . 'media/img/vintage-bg.jpg' }});background-repeat: repeat;">
                        <div class="pt-5 py-sm-4 px-4 ps-md-4 pe-4 text-center text-sm-start pb-4 pb-md-0">
                                <h2 class="font-title">{{ $widget['title'] }}</h2>
                                <p class="text-muted pb-2">{{ $widget['subtitle'] }}</p><a class="btn btn-primary" aria-label="Pogledajte ponudu" href="{{ url($widget['url']) }}">Pogledajte ponudu <i class="ci-arrow-right ms-2 me-n1"></i></a>
                        </div>
                    <a  href="{{ url($widget['url']) }}">   <img class="d-block mx-auto mx-sm-0 rounded-end pb-4 pb-sm-0 " width="290" height="290" src="{{ $widget['image'] }}" style="max-width:290px" alt="{{ $widget['title'] }}"></a>
                </div>
            </div>
        @endforeach
    </div>
</section>
