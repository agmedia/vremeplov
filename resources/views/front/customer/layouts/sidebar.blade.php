<aside class="col-lg-4 pt-4 pt-lg-0 pe-xl-5">
    <div class="bg-white rounded-3 shadow-lg pt-1 mb-5 mb-lg-0">
        <div class="d-md-flex justify-content-between align-items-center text-center text-md-start p-4">
            <div class="d-md-flex align-items-center">

                <div class="ps-md-0">
                    <h3 class=" mb-0">{{ $user->details->fname ? $user->details->fname . ' ' . $user->details->lname: $user->name }}</h3><span class="text-primary-dark fs-lg">{{ $user->email }}</span>
                </div>
            </div><a class="btn btn-primary d-lg-none mb-2 mt-3 mt-md-0" href="#account-menu" data-bs-toggle="collapse" aria-expanded="false"><i class="ci-menu me-2"></i>Navigacija</a>
        </div>
        <div class="d-lg-block collapse" id="account-menu">
            <div class="bg-secondary px-4 py-3">
                <h3 class="fs-sm mb-0 text-muted">Moj korisnički račun</h3>
            </div>
            <ul class="list-unstyled mb-0">
                <li class="border-bottom mb-0">
                    <a class="nav-link-style d-flex align-items-center px-4 py-3 {{ request()->routeIs('moj-racun') ? 'active' : '' }}" href="{{ route('moj-racun') }}">
                        <i class="ci-user opacity-60 me-2"></i>Moji podaci
                    </a>
                </li>

                <li class="border-bottom mb-0">
                    <a class="nav-link-style d-flex align-items-center px-4 py-3 {{ request()->routeIs('moje-narudzbe') ? 'active' : '' }}" href="{{ route('moje-narudzbe') }}">
                        <i class="ci-bag opacity-60 me-2"></i>Narudžbe
                    </a>
                </li>

                <li class="mb-0">
                    <a class="nav-link-style d-flex align-items-center px-4 py-3" href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="ci-sign-out opacity-60 me-2"></i>Odjava
                    </a>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                </li>
            </ul>
        </div>
    </div>
</aside>
