@php
    $editing = isset($circular);
@endphp

<div class="row g-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold">Basic Information</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $circular->title ?? '') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Category <span class="text-danger">*</span></label>
                    <select name="category" class="form-select" required>
                        <option value="">Select category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" @selected(old('category', $circular->category ?? '') === $category)>{{ ucfirst($category) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Summary <span class="text-danger">*</span></label>
                    <textarea name="summary" class="form-control" maxlength="200" rows="3" required>{{ old('summary', $circular->summary ?? '') }}</textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Priority <span class="text-danger">*</span></label>
                    <select name="priority" class="form-select" required>
                        <option value="">Select priority</option>
                        @foreach($priorities as $priority)
                            <option value="{{ $priority }}" @selected(old('priority', $circular->priority ?? 'normal') === $priority)>{{ ucfirst($priority) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" @selected(old('status', $circular->status ?? 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $circular->status ?? 'active') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Publish Date <span class="text-danger">*</span></label>
                    <input type="datetime-local" name="publish_date" class="form-control" value="{{ old('publish_date', isset($circular?->publish_date) ? $circular->publish_date->format('Y-m-d\TH:i') : '') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Expiry Date</label>
                    <input type="datetime-local" name="expiry_date" class="form-control" value="{{ old('expiry_date', isset($circular?->expiry_date) ? $circular->expiry_date->format('Y-m-d\TH:i') : '') }}">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_pinned" id="isPinned" value="1" @checked(old('is_pinned', $circular->is_pinned ?? false))>
                        <label class="form-check-label" for="isPinned">Pin this circular</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold">Content</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Featured Image</label>
                    <input type="file" name="featured_image" class="form-control" accept="image/*">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Attachment (PDF, DOC, Image)</label>
                    <input type="file" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Content <span class="text-danger">*</span></label>
                    <textarea name="content" class="form-control" rows="8" required>{{ old('content', $circular->content ?? '') }}</textarea>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Video URL</label>
                    <input type="url" name="video_url" class="form-control" value="{{ old('video_url', $circular->video_url ?? '') }}" placeholder="https://">
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold">Target Audience</div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Audience Type <span class="text-danger">*</span></label>
                    <select name="audience_type" class="form-select" required>
                        <option value="">Select audience</option>
                        @foreach($audiences as $audience)
                            <option value="{{ $audience }}" @selected(old('audience_type', $circular->audience_type ?? '') === $audience)>{{ ucfirst(str_replace('_', ' ', $audience)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">City</label>
                    <select name="city_id" class="form-select">
                        <option value="">All cities</option>
                        @foreach($cities as $city)
                            <option value="{{ $city->id }}" @selected(old('city_id', $circular->city_id ?? '') == $city->id)>{{ $city->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Circle</label>
                    <select name="circle_id" class="form-select">
                        <option value="">All circles</option>
                        @foreach($circles as $circle)
                            <option value="{{ $circle->id }}" @selected(old('circle_id', $circular->circle_id ?? '') == $circle->id)>{{ $circle->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="send_push_notification" id="sendPushNotification" value="1" @checked(old('send_push_notification', $circular->send_push_notification ?? false))>
                        <label class="form-check-label" for="sendPushNotification">Send push notification</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="allow_comments" id="allowComments" value="1" @checked(old('allow_comments', $circular->allow_comments ?? false))>
                        <label class="form-check-label" for="allowComments">Allow comments</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <button class="btn btn-primary">{{ $editing ? 'Update Circular' : 'Create Circular' }}</button>
    <a href="{{ route('admin.circulars.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
