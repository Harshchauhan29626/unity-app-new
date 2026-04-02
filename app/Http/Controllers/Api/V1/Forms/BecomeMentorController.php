<?php

namespace App\Http\Controllers\Api\V1\Forms;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Forms\SubmitBecomeMentorRequest;
use App\Http\Resources\BecomeMentorSubmissionResource;
use App\Mail\WebsiteFormConfirmationMail;
use App\Models\BecomeMentorSubmission;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class BecomeMentorController extends BaseApiController
{
    public function index(Request $request)
    {
        $query = BecomeMentorSubmission::query();

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('first_name', 'ilike', '%' . $search . '%')
                    ->orWhere('last_name', 'ilike', '%' . $search . '%')
                    ->orWhere('email', 'ilike', '%' . $search . '%')
                    ->orWhere('city', 'ilike', '%' . $search . '%');
            });
        }

        if ($status = trim((string) $request->query('status', ''))) {
            $query->where('status', $status);
        }

        if ($fromDate = $request->query('from_date')) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate = $request->query('to_date')) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $items = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Submissions fetched successfully.',
            'data' => BecomeMentorSubmissionResource::collection($items->items()),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(string $id)
    {
        $submission = BecomeMentorSubmission::find($id);

        if (! $submission) {
            return response()->json([
                'status' => false,
                'message' => 'Submission not found.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Submission fetched successfully.',
            'data' => new BecomeMentorSubmissionResource($submission),
        ]);
    }

    public function submit(SubmitBecomeMentorRequest $request)
    {
        $data = $request->validated();

        Log::info('Mentor form submission started', [
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'ip' => $request->ip(),
        ]);

        try {
            $recentDuplicateExists = BecomeMentorSubmission::query()
                ->where('email', $data['email'])
                ->where('phone', $data['phone'])
                ->where('created_at', '>=', now()->subMinutes(10))
                ->exists();

            if ($recentDuplicateExists) {
                Log::warning('Mentor form submission blocked as duplicate', [
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'A similar submission was received recently. Please try again after some time.',
                    'data' => null,
                ], 429);
            }

            $submission = BecomeMentorSubmission::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'city' => $data['city'],
                'linkedin_profile' => $data['linkedin_profile'],
                'status' => 'new',
            ]);

            $this->sendConfirmationEmail(
                email: $submission->email,
                recipientName: trim($submission->first_name . ' ' . $submission->last_name) ?: $submission->first_name,
                subject: 'Your Mentor Application Has Been Received',
                formTitle: 'Become a Mentor',
                confirmationMessage: 'We have successfully received your “Become a Mentor” form submission on Peers Global. Our team will review your details and connect with you soon.',
                submissionId: (string) $submission->id,
            );

            Log::info('Mentor form submission stored successfully', [
                'submission_id' => $submission->id,
                'email' => $submission->email,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Mentor form submitted successfully.',
                'data' => new BecomeMentorSubmissionResource($submission),
            ], 201);
        } catch (\Throwable $exception) {
            Log::error('Mentor form submission failed', [
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'ip' => $request->ip(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Unable to submit mentor form right now. Please try again later.',
                'data' => null,
            ], 500);
        }
    }

    private function sendConfirmationEmail(
        string $email,
        string $recipientName,
        string $subject,
        string $formTitle,
        string $confirmationMessage,
        string $submissionId,
    ): void {
        $mailable = new WebsiteFormConfirmationMail($subject, $recipientName, $formTitle, $confirmationMessage);

        try {
            Log::info('Mentor confirmation email send started', [
                'email' => $email,
                'submission_id' => $submissionId,
                'mailer' => config('mail.default'),
            ]);

            Mail::to($email)->send($mailable);

            Log::info('Mentor confirmation email sent successfully', [
                'email' => $email,
                'submission_id' => $submissionId,
            ]);

            try {
                app(EmailLogService::class)->logMailableSent($mailable, [
                    'to_email' => $email,
                    'to_name' => $recipientName,
                    'template_key' => 'website_form_confirmation',
                    'source_module' => 'WebsiteForms',
                    'related_type' => BecomeMentorSubmission::class,
                    'related_id' => $submissionId,
                    'payload' => ['form_title' => $formTitle],
                ]);
            } catch (\Throwable $logException) {
                Log::warning('Mentor confirmation email sent but email-log write failed', [
                    'email' => $email,
                    'submission_id' => $submissionId,
                    'error' => $logException->getMessage(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('Mentor confirmation email failed', [
                'email' => $email,
                'submission_id' => $submissionId,
                'error' => $exception->getMessage(),
            ]);

            try {
                app(EmailLogService::class)->logMailableFailed($mailable, [
                    'to_email' => $email,
                    'to_name' => $recipientName,
                    'template_key' => 'website_form_confirmation',
                    'source_module' => 'WebsiteForms',
                    'related_type' => BecomeMentorSubmission::class,
                    'related_id' => $submissionId,
                    'payload' => ['form_title' => $formTitle],
                ], $exception);
            } catch (\Throwable $logException) {
                Log::warning('Mentor confirmation email failed and email-log write failed', [
                    'email' => $email,
                    'submission_id' => $submissionId,
                    'error' => $logException->getMessage(),
                ]);
            }
        }
    }
}
