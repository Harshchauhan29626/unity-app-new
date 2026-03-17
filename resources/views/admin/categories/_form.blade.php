<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Category Name <span class="text-danger">*</span></label>
        <input type="text" name="category_name" class="form-control" value="{{ old('category_name', $category->category_name) }}" required maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label">Sector</label>
        <input type="text" name="sector" class="form-control" value="{{ old('sector', $category->sector) }}" maxlength="255">
    </div>
    <div class="col-12">
        <label class="form-label">Remarks</label>
        <textarea name="remarks" class="form-control" rows="3">{{ old('remarks', $category->remarks) }}</textarea>
    </div>
</div>
