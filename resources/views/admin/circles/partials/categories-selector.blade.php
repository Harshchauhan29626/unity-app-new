<div class="col-12">
    <label class="form-label d-block">Categories</label>
    <div class="form-text mb-2">Select a category from dropdown and click Add</div>
    <div class="row g-3 align-items-start">
        <div class="col-lg-6">
            <div class="input-group mb-2">
                <select id="categoryPicker" class="form-select">
                    <option value="">Select category</option>
                    @foreach(($categories ?? collect()) as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-outline-primary" id="addCategoryBtn">Add Category</button>
            </div>
            <div class="border rounded p-3 bg-white" style="max-height: 220px; overflow-y: auto;">
                <div class="small fw-semibold text-muted mb-2">Available Categories</div>
                <div class="row g-2" id="categoryCheckboxList">
                    @forelse(($categories ?? collect()) as $category)
                        <div class="col-12">
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="categories[]"
                                    value="{{ $category->id }}"
                                    data-category-name="{{ $category->name }}"
                                    id="category_{{ $category->id }}"
                                    @checked(in_array($category->id, $selectedCategoryIds ?? []))
                                >
                                <label class="form-check-label" for="category_{{ $category->id }}">
                                    {{ $category->name }}
                                </label>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="text-muted small">No categories available.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="border rounded p-3 bg-white" style="max-height: 220px; overflow-y: auto;">
                <div class="small fw-semibold text-muted mb-2">Selected Categories</div>
                <div id="selectedCategoryPreview" class="d-flex flex-wrap gap-2"></div>
            </div>
        </div>
    </div>
    @error('categories')
        <div class="text-danger small mt-2">{{ $message }}</div>
    @enderror
    @error('categories.*')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>
