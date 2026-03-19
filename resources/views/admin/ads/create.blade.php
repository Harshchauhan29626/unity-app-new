@extends('admin.layouts.app')

@section('title', 'Create Ad')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Create Ad</h1>
    <a href="{{ route('admin.ads.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.ads.store') }}" enctype="multipart/form-data">
            @csrf
            @include('admin.ads._form')
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
@endsection
