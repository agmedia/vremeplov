<div class="steps steps-light pt-2 pb-3 mb-5">
    <a class="step-item {{ request()->routeIs(['kosarica']) ? 'current' : '' }} {{ request()->routeIs(['kosarica', 'adresa-isporuke', 'dostava', 'naplata','pregled']) ? 'active' : '' }}" href="{{ route('kosarica') }}">
        <div class="step-progress"><span class="step-count">1</span></div>
        <div class="step-label"><i class="fa-regular fa-cart-shopping"></i>Košarica</div>
    </a>
    <a class="step-item  {{ request()->routeIs(['adresa-isporuke']) ? 'current' : '' }} {{ request()->routeIs([ 'adresa-isporuke', 'dostava', 'naplata','pregled']) ? 'active' : '' }}" href="{{ route('adresa-isporuke') }}">
        <div class="step-progress"><span class="step-count">2</span></div>
        <div class="step-label"><i class="fa-regular fa-circle-user"></i>Podaci</div>
    </a>
    <a class="step-item  {{ request()->routeIs(['dostava']) ? 'current' : '' }} {{ request()->routeIs([ 'dostava', 'naplata','pregled']) ? 'active' : '' }}" href="{{ route('dostava') }}">
        <div class="step-progress"><span class="step-count">3</span></div>
        <div class="step-label"><i class="fa-regular fa-box"></i>Dostava</div></a>
    <a class="step-item  {{ request()->routeIs(['naplata']) ? 'current' : '' }} {{ request()->routeIs([ 'naplata','pregled']) ? 'active' : '' }}" href="{{ route('naplata') }}">
        <div class="step-progress"><span class="step-count">4</span></div>
        <div class="step-label"><i class="fa-regular fa-credit-card"></i>Plaćanje</div>
    </a>
    <a class="step-item  {{ request()->routeIs(['pregled']) ? 'current' : '' }} {{ request()->routeIs([ 'pregled']) ? 'active' : '' }}" href="{{ route('pregled') }}">
        <div class="step-progress"><span class="step-count">5</span></div>
        <div class="step-label"><i class="fa-regular fa-circle-check"></i>Pregledaj</div>
    </a>
</div>
