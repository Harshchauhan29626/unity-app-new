<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Category Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $category->name) }}" required maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label">Slug</label>
        <input type="text" name="slug" class="form-control" value="{{ old('slug', $category->slug) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label">Circle Key</label>
        <input type="text" name="circle_key" class="form-control" value="{{ old('circle_key', $category->circle_key) }}" maxlength="255">
    </div>
    <div class="col-md-3">
        <label class="form-label">Level</label>
        <input type="number" name="level" class="form-control" value="{{ old('level', $category->level ?? 1) }}" min="1" readonly>
    </div>
    <div class="col-md-3">
        <label class="form-label">Sort Order</label>
        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0">
    </div>
    <div class="col-12">
        <div class="form-check mt-1">
            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" {{ old('is_active', $category->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
    </div>
</div>
