@if($errors->any())
<div class="pf-alert pf-alert--danger" style="margin-bottom:18px" role="alert">
    <x-pf-icon name="alert" />
    <div>
        <strong>Revisa los datos:</strong>
        <ul style="margin:4px 0 0 16px;list-style:disc">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
</div>
@endif
