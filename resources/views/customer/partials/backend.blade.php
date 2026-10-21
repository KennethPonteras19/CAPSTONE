<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('js/customer-workspace.js') }}"></script>
<script>
    let customerCart = {{ \Illuminate\Support\Js::from($customerCart) }};
</script>
