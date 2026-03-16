@extends('admin.layouts.app')

@section('title', 'Create Circular')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Create Circular</h5>
    <a href="{{ route('admin.circulars.index') }}" class="btn btn-outline-secondary btn-sm">Back to Circulars</a>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('admin.circulars.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @include('admin.circulars._form')
</form>
@endsection
