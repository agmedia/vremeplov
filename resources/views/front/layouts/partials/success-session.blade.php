@if(session('success'))
    <!-- Success alert -->
    <div class="alert alert-success d-flex" role="alert">
        <div class="alert-icon">
            <i class="fa-regular fa-circle-check"></i>
        </div>
        <div>Uspjeh..! {{ session('success') }}</div>
    </div>
@endif
