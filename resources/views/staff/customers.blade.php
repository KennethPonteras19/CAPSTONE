@extends('staff.layout')
@section('title', 'Customers')
@section('subtitle', 'Manage customer contact details and view saved orders and purchase history.')
@section('actions')@moduleRoute('staff', 'customers')<a class="button" href="{{ route('customers') }}#customerForm">Add Customer</a>@endmoduleRoute
@endsection
@section('content')
<form class="workspace-toolbar" method="GET"><input name="search" value="{{ request('search') }}" placeholder="Search name, phone, email, or address…" aria-label="Search customers" maxlength="255">
<select name="sort" aria-label="Sort"><option value="asc">A–Z</option><option value="desc" @selected(request('sort') === 'desc')>Z–A</option></select><button>Search</button>@moduleRoute('staff', 'customers')<a href="{{ route('customers') }}">Clear</a>@endmoduleRoute</form>
<div class="two-columns">
<section class="panel"><h2>Customer Directory</h2><div class="table-scroll"><table class="workspace-table"><thead><tr><th>Name</th><th>Phone</th><th></th></tr></thead><tbody>
@forelse($customers as $customer)<tr><td>{{ $customer->name }}@if(!$customer->is_active) <span class="badge">Inactive</span>@endif</td><td>{{ $customer->phone ?? '—' }}</td><td>@moduleRoute('staff', 'customers')<a href="{{ route('customers', array_merge(request()->except(['customer', 'history_page']), ['customer' => $customer->id])) }}">View / Edit</a>@endmoduleRoute</td></tr>
@empty<tr><td colspan="3" class="empty-state">No customers found.</td></tr>@endforelse
</tbody></table></div>@include('staff.partials.pagination', ['paginator' => $customers])</section>
<section class="panel" id="customerForm"><h2>{{ $selected ? 'Customer Details' : 'Add Customer' }}</h2>
<form method="POST" action="{{ $selected ? route('staff.customers.update', $selected) : route('staff.customers.store') }}">@csrf @if($selected) @method('PUT') @endif
<div class="form-grid"><label class="full">Name<input name="name" value="{{ old('name', $selected?->name) }}" required maxlength="255" autocomplete="name"></label>
<label>Phone<input name="phone" value="{{ old('phone', $selected?->phone) }}" maxlength="40" autocomplete="tel"></label><label>Email<input type="email" name="email" value="{{ old('email', $selected?->email) }}" maxlength="255" autocomplete="email"></label>
<label class="full">Address<textarea name="address" maxlength="2000" autocomplete="street-address">{{ old('address', $selected?->address) }}</textarea></label></div>
<div class="form-actions"><button>Save Customer</button>@if($selected)@moduleRoute('staff', 'customers')<a href="{{ route('customers') }}">Add another</a>@endmoduleRoute
@endif</div>
</form>
</section>
</div>
@if($selected)<section class="panel"><h2>Orders for {{ $selected->name }}</h2>@include('staff.partials.order-table', ['orders' => $history])@include('staff.partials.pagination', ['paginator' => $history])</section>@endif
@endsection
