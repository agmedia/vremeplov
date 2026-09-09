<!-- Toolbar for handheld devices (Marketplace)-->
<div class="handheld-toolbar d-none">
    <div class="d-table table-layout-fixed w-100">
        @if (Request::is(\App\Helpers\Helper::categoryGroupPath(true) . '/*' ) || Request::is(\App\Helpers\Helper::categoryGroupPath(true)))
       <!--     <a class="d-table-cell handheld-toolbar-item" href="#" data-bs-toggle="offcanvas" data-bs-target="#shop-sidebar"><span class="handheld-toolbar-icon"><i class="fa-regular fa-filter"></i></span><span class="handheld-toolbar-label">Filtriraj</span></a> -->
        @endif

        <cart-footer-icon carturl="{{ route('kosarica') }}"></cart-footer-icon>
    </div>
</div>
