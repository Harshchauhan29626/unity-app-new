@extends('admin.layouts.app')

@section('title', 'View Circle Category')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">View Circle Category</h1>
    <a href="{{ route('admin.categories.index') }}" class="btn btn-sm btn-outline-secondary">Back to List</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Main Category Details</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6"><strong>ID:</strong> {{ $category->id }}</div>
                    <div class="col-md-6"><strong>Name:</strong> {{ $category->name }}</div>
                    <div class="col-md-6"><strong>Slug:</strong> {{ $category->slug ?: '—' }}</div>
                    <div class="col-md-6"><strong>Circle Key:</strong> {{ $category->circle_key ?: '—' }}</div>
                    <div class="col-md-6"><strong>Level:</strong> {{ $category->level }}</div>
                    <div class="col-md-6"><strong>Sort Order:</strong> {{ $category->sort_order }}</div>
                    <div class="col-md-6"><strong>Active:</strong> {{ $category->is_active ? 'Yes' : 'No' }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Child Category Summary</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>Level 2 count</span>
                        <span class="fw-semibold">{{ $level2Count }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>Level 3 count</span>
                        <span class="fw-semibold">{{ $level3Count }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>Level 4 count</span>
                        <span class="fw-semibold">{{ $level4Count }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>Total child categories</span>
                        <span class="fw-bold">{{ $totalChildren }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>


<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">Add Child Categories</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-4">
                <h6 class="mb-2">Add Level 2 Category</h6>
                <form method="POST" action="{{ route('admin.categories.level2.store', $category) }}" class="d-grid gap-2">
                    @csrf
                    <input type="text" name="name" class="form-control" placeholder="Level 2 Category Name" required>
                    <button type="submit" class="btn btn-primary btn-sm">Add Level 2</button>
                </form>
            </div>

            <div class="col-lg-4">
                <h6 class="mb-2">Add Level 3 Category</h6>
                <form method="POST" action="{{ route('admin.categories.level3.store', $category) }}" class="d-grid gap-2">
                    @csrf
                    <select name="level2_id" class="form-select" required>
                        <option value="">Select Level 2 Parent</option>
                        @foreach($level2Options as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="name" class="form-control" placeholder="Level 3 Category Name" required>
                    <button type="submit" class="btn btn-primary btn-sm">Add Level 3</button>
                </form>
            </div>

            <div class="col-lg-4">
                <h6 class="mb-2">Add Level 4 Category</h6>
                <form method="POST" action="{{ route('admin.categories.level4.store', $category) }}" class="d-grid gap-2" id="add-level4-form">
                    @csrf
                    <select name="level2_id" class="form-select" id="level4-level2" required>
                        <option value="">Select Level 2 Parent</option>
                        @foreach($level2Options as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    <select name="level3_id" class="form-select" id="level4-level3" required>
                        <option value="">Select Level 3 Parent</option>
                        @foreach($level3Options as $option)
                            <option value="{{ $option->id }}" data-level2-id="{{ $option->level2_id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="name" class="form-control" placeholder="Level 4 Category Name" required>
                    <button type="submit" class="btn btn-primary btn-sm">Add Level 4</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-semibold">Hierarchical Category Tree</div>
    <div class="card-body">
        @if(empty($children))
            <p class="text-muted mb-0">No child categories found for this main category.</p>
        @else
            <div class="small text-muted mb-2">Main Category: <strong class="text-dark">{{ $category->name }}</strong></div>
            @foreach($children as $level2Node)
                <div class="border rounded p-3 mb-3">
                    <div class="fw-semibold mb-2">Level 2: {{ $level2Node['category']->name }}</div>

                    @if(empty($level2Node['children']))
                        <div class="text-muted ms-2">No level 3 categories.</div>
                    @else
                        @foreach($level2Node['children'] as $level3Node)
                            <div class="ms-3 border-start ps-3 mb-2">
                                <div class="fw-medium">Level 3: {{ $level3Node['category']->name }}</div>

                                @if(empty($level3Node['children']))
                                    <div class="text-muted ms-2">No level 4 categories.</div>
                                @else
                                    <ul class="mb-0 mt-1">
                                        @foreach($level3Node['children'] as $level4Category)
                                            <li>Level 4: {{ $level4Category->name }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const level2Select = document.getElementById('level4-level2');
    const level3Select = document.getElementById('level4-level3');

    if (!level2Select || !level3Select) {
        return;
    }

    const originalOptions = Array.from(level3Select.querySelectorAll('option')).map((option) => option.cloneNode(true));

    const renderLevel3Options = () => {
        const selectedLevel2 = level2Select.value;
        level3Select.innerHTML = '';

        originalOptions.forEach((option) => {
            if (!option.value) {
                level3Select.appendChild(option.cloneNode(true));
                return;
            }

            if (selectedLevel2 && option.dataset.level2Id === selectedLevel2) {
                level3Select.appendChild(option.cloneNode(true));
            }
        });

        if (level3Select.options.length <= 1) {
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = selectedLevel2 ? 'No Level 3 categories for selected Level 2' : 'Select Level 3 Parent';
            level3Select.appendChild(placeholder);
        }
    };

    level2Select.addEventListener('change', renderLevel3Options);
    renderLevel3Options();
});
</script>
@endpush
