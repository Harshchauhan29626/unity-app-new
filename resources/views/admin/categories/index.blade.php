@extends('admin.layouts.app')

@section('title', 'Circle Categories')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Circle Categories</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.categories.export', request()->query()) }}" class="btn btn-success btn-sm">
            Export
        </a>

        <form action="{{ route('admin.categories.import') }}" method="POST" enctype="multipart/form-data" class="d-flex gap-2">
            @csrf
            <input type="file" name="file" required class="form-control form-control-sm d-inline-block" style="width:200px;">
            <button type="submit" class="btn btn-primary btn-sm">Import</button>
        </form>

        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary btn-sm">Add Category</a>
    </div>
</div>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-sm-4">
                <input type="text" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search category name">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary">Search</button>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th style="width: 210px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>{{ $category->id }}</td>
                        <td>{{ $category->name }}</td>
                        <td>
                            <div class="d-flex gap-1">
                                <a href="{{ route('admin.categories.view', $category) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted py-3">No categories found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        {{ $categories->links() }}
    </div>
</div>
@endsection
