<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BecomeMentorSubmission;
use App\Models\BecomeSpeakerSubmission;
use App\Models\EntrepreneurCertificationSubmission;
use App\Models\LeadershipCertificationSubmission;
use App\Models\SmeBusinessStorySubmission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadSubmissionsController extends Controller
{
    public function entrepreneurCertification(Request $request): View
    {
        return $this->renderIndex($request, 'entrepreneur_certification');
    }

    public function entrepreneurCertificationShow(string $id): View
    {
        return $this->renderShow('entrepreneur_certification', $id);
    }

    public function leadershipCertification(Request $request): View
    {
        return $this->renderIndex($request, 'leadership_certification');
    }

    public function leadershipCertificationShow(string $id): View
    {
        return $this->renderShow('leadership_certification', $id);
    }

    public function partnerWithUs(Request $request): View
    {
        return $this->renderIndex($request, 'partner_with_us');
    }

    public function partnerWithUsShow(string $id): View
    {
        return $this->renderShow('partner_with_us', $id);
    }

    public function becomeSpeaker(Request $request): View
    {
        return $this->renderIndex($request, 'become_speaker');
    }

    public function becomeSpeakerShow(string $id): View
    {
        return $this->renderShow('become_speaker', $id);
    }

    public function becomeMentor(Request $request): View
    {
        return $this->renderIndex($request, 'become_mentor');
    }

    public function becomeMentorShow(string $id): View
    {
        return $this->renderShow('become_mentor', $id);
    }

    protected function renderIndex(Request $request, string $key): View
    {
        $resource = $this->resourceConfig($key);
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', 'all'));
        $fromDate = trim((string) $request->query('from_date', ''));
        $toDate = trim((string) $request->query('to_date', ''));
        $columnFilters = [
            'name' => trim((string) $request->query('name', '')),
            'phone' => trim((string) $request->query('phone', '')),
            'city' => trim((string) $request->query('city', '')),
            'email' => trim((string) $request->query('email', '')),
            'company' => trim((string) $request->query('company', '')),
        ];

        /** @var Builder $query */
        $query = $resource['model']::query()->orderByDesc('created_at');

        if ($search !== '') {
            $like = "%{$search}%";
            $searchColumns = $resource['search_columns'];

            $query->where(function (Builder $builder) use ($searchColumns, $like) {
                foreach ($searchColumns as $index => $column) {
                    if ($index === 0) {
                        $builder->where($column, 'ILIKE', $like);
                        continue;
                    }

                    $builder->orWhere($column, 'ILIKE', $like);
                }
            });
        }

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($fromDate !== '') {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate !== '') {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $this->applyColumnFilters($query, $resource, $columnFilters);

        /** @var LengthAwarePaginator $items */
        $items = $query->paginate(25)->appends($request->query());

        $statuses = $resource['model']::query()
            ->select('status')
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter(fn ($value) => $value !== '')
            ->values();

        return view('admin.leads.index', [
            'resource' => $resource,
            'items' => $items,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'name' => $columnFilters['name'],
                'phone' => $columnFilters['phone'],
                'city' => $columnFilters['city'],
                'email' => $columnFilters['email'],
                'company' => $columnFilters['company'],
            ],
            'statuses' => $statuses,
        ]);
    }

    protected function applyColumnFilters(Builder $query, array $resource, array $filters): void
    {
        $fieldMap = $resource['filter_fields'] ?? [];

        if (($filters['name'] ?? '') !== '' && isset($fieldMap['name'])) {
            $like = '%'.$filters['name'].'%';
            $nameColumns = (array) $fieldMap['name'];
            $query->where(function (Builder $builder) use ($nameColumns, $like) {
                foreach ($nameColumns as $index => $column) {
                    if ($index === 0) {
                        $builder->where($column, 'ILIKE', $like);
                    } else {
                        $builder->orWhere($column, 'ILIKE', $like);
                    }
                }
            });
        }

        foreach (['phone', 'city', 'email', 'company'] as $filterKey) {
            $filterValue = $filters[$filterKey] ?? '';
            if ($filterValue === '' || ! isset($fieldMap[$filterKey])) {
                continue;
            }

            $query->where($fieldMap[$filterKey], 'ILIKE', '%'.$filterValue.'%');
        }
    }

    protected function renderShow(string $key, string $id): View
    {
        $resource = $this->resourceConfig($key);

        /** @var Model $item */
        $item = $resource['model']::query()->findOrFail($id);

        return view('admin.leads.show', [
            'resource' => $resource,
            'item' => $item,
        ]);
    }

    protected function resourceConfig(string $key): array
    {
        $resources = [
            'entrepreneur_certification' => [
                'key' => 'entrepreneur_certification',
                'title' => 'Entrepreneur Certification Leads',
                'menu_label' => 'Entrepreneur Certification',
                'model' => EntrepreneurCertificationSubmission::class,
                'index_route' => 'admin.leads.entrepreneur-certification.index',
                'show_route' => 'admin.leads.entrepreneur-certification.show',
                'search_columns' => ['id', 'full_name', 'business_name', 'email', 'contact_no', 'notes'],
                'columns' => ['id', 'full_name', 'business_name', 'email', 'contact_no', 'status', 'notes', 'created_at', 'updated_at'],
                'long_text_columns' => ['notes'],
                'filter_fields' => [
                    'name' => ['full_name'],
                    'phone' => 'contact_no',
                    'email' => 'email',
                    'company' => 'business_name',
                ],
            ],
            'leadership_certification' => [
                'key' => 'leadership_certification',
                'title' => 'Leadership Certification Leads',
                'menu_label' => 'Leadership Certification',
                'model' => LeadershipCertificationSubmission::class,
                'index_route' => 'admin.leads.leadership-certification.index',
                'show_route' => 'admin.leads.leadership-certification.show',
                'search_columns' => ['id', 'full_name', 'business_name', 'email', 'contact_no', 'notes'],
                'columns' => ['id', 'full_name', 'business_name', 'email', 'contact_no', 'status', 'notes', 'created_at', 'updated_at'],
                'long_text_columns' => ['notes'],
                'filter_fields' => [
                    'name' => ['full_name'],
                    'phone' => 'contact_no',
                    'email' => 'email',
                    'company' => 'business_name',
                ],
            ],
            'partner_with_us' => [
                'key' => 'partner_with_us',
                'title' => 'Partner With Us Leads',
                'menu_label' => 'Partner With Us',
                'model' => SmeBusinessStorySubmission::class,
                'index_route' => 'admin.leads.partner-with-us.index',
                'show_route' => 'admin.leads.partner-with-us.show',
                'search_columns' => ['id', 'full_name', 'email', 'contact_number', 'business_name', 'company_introduction', 'co_founders_and_partners_details', 'notes'],
                'columns' => ['id', 'full_name', 'email', 'contact_number', 'business_name', 'company_introduction', 'co_founders_and_partners_details', 'status', 'notes', 'created_at', 'updated_at'],
                'long_text_columns' => ['company_introduction', 'co_founders_and_partners_details', 'notes'],
                'filter_fields' => [
                    'name' => ['full_name'],
                    'phone' => 'contact_number',
                    'email' => 'email',
                    'company' => 'business_name',
                ],
            ],
            'become_speaker' => [
                'key' => 'become_speaker',
                'title' => 'Become Speaker Leads',
                'menu_label' => 'Become Speaker',
                'model' => BecomeSpeakerSubmission::class,
                'index_route' => 'admin.leads.become-speaker.index',
                'show_route' => 'admin.leads.become-speaker.show',
                'search_columns' => ['id', 'first_name', 'last_name', 'email', 'phone', 'city', 'linkedin_profile_url', 'company_name', 'brief_bio', 'topics_to_speak_on', 'notes'],
                'columns' => ['id', 'first_name', 'last_name', 'email', 'phone', 'city', 'linkedin_profile_url', 'company_name', 'brief_bio', 'topics_to_speak_on', 'image_file_id', 'image_url', 'status', 'notes', 'created_at', 'updated_at'],
                'long_text_columns' => ['brief_bio', 'topics_to_speak_on', 'notes'],
                'filter_fields' => [
                    'name' => ['first_name', 'last_name'],
                    'phone' => 'phone',
                    'city' => 'city',
                    'email' => 'email',
                    'company' => 'company_name',
                ],
            ],
            'become_mentor' => [
                'key' => 'become_mentor',
                'title' => 'Become Mentor Leads',
                'menu_label' => 'Become Mentor',
                'model' => BecomeMentorSubmission::class,
                'index_route' => 'admin.leads.become-mentor.index',
                'show_route' => 'admin.leads.become-mentor.show',
                'search_columns' => ['id', 'first_name', 'last_name', 'email', 'phone', 'city', 'linkedin_profile', 'notes'],
                'columns' => ['id', 'first_name', 'last_name', 'email', 'phone', 'city', 'linkedin_profile', 'status', 'notes', 'created_at', 'updated_at'],
                'long_text_columns' => ['notes'],
                'filter_fields' => [
                    'name' => ['first_name', 'last_name'],
                    'phone' => 'phone',
                    'city' => 'city',
                    'email' => 'email',
                ],
            ],
        ];

        abort_unless(isset($resources[$key]), 404);

        return $resources[$key];
    }
}
