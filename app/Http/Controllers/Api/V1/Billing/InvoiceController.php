<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Billing\InvoiceDetailResource;
use App\Http\Resources\Billing\InvoiceListItemResource;
use App\Models\User;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class InvoiceController extends BaseApiController
{
    public function __construct(private readonly ZohoBillingService $zohoBillingService)
    {
    }

    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);

        if (! $this->zohoBillingService->hasUserZohoMapping($user)) {
            return $this->success([
                'items' => [],
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'has_more_page' => false,
                    'total' => 0,
                ],
            ], 'No Zoho billing account linked for this user.');
        }

        try {
            $result = $this->zohoBillingService->listInvoicesForUser($user, $page, $perPage);
            $invoices = is_array($result['invoices'] ?? null) ? $result['invoices'] : [];
            $pageContext = is_array($result['page_context'] ?? null) ? $result['page_context'] : [];

            return $this->success([
                'items' => InvoiceListItemResource::collection(collect($invoices)),
                'pagination' => [
                    'page' => (int) ($pageContext['page'] ?? $page),
                    'per_page' => (int) ($pageContext['per_page'] ?? $perPage),
                    'has_more_page' => (bool) ($pageContext['has_more_page'] ?? false),
                    'total' => isset($pageContext['total']) ? (int) $pageContext['total'] : count($invoices),
                ],
            ]);
        } catch (RuntimeException $runtimeException) {
            return $this->error('Failed to fetch invoices.', (int) $runtimeException->getCode() >= 400 ? (int) $runtimeException->getCode() : 500);
        } catch (Throwable $throwable) {
            return $this->error('Failed to fetch invoices.', 500);
        }
    }

    public function show(Request $request, string $invoiceId)
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->zohoBillingService->hasUserZohoMapping($user)) {
            return $this->error('No Zoho billing account linked for this user.', 404);
        }

        try {
            $invoice = $this->zohoBillingService->getInvoiceForUser($user, $invoiceId);

            if (! is_array($invoice)) {
                return $this->error('Invoice not found.', 404);
            }

            return $this->success(new InvoiceDetailResource($invoice));
        } catch (RuntimeException $runtimeException) {
            if ((int) $runtimeException->getCode() === 404) {
                return $this->error('Invoice not found.', 404);
            }

            return $this->error('Failed to fetch invoice detail.', (int) $runtimeException->getCode() >= 400 ? (int) $runtimeException->getCode() : 500);
        } catch (Throwable $throwable) {
            return $this->error('Failed to fetch invoice detail.', 500);
        }
    }

    public function pdf(Request $request, string $invoiceId)
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->zohoBillingService->hasUserZohoMapping($user)) {
            return $this->error('Invoice not found.', 404);
        }

        try {
            $pdf = $this->zohoBillingService->getInvoicePdfForUser($user, $invoiceId);

            if (! is_array($pdf) || (string) ($pdf['content'] ?? '') === '') {
                return $this->error('Invoice not found.', 404);
            }

            $invoiceNumber = (string) ($pdf['invoice_number'] ?? $invoiceId);
            $safeInvoiceNumber = Str::of($invoiceNumber)->replaceMatches('/[^A-Za-z0-9\\-_]/', '-')->toString();
            $filename = 'invoice-' . trim($safeInvoiceNumber, '-') . '.pdf';

            return response()->stream(
                fn () => print($pdf['content']),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $filename . '"',
                    'Content-Length' => (string) strlen((string) $pdf['content']),
                ]
            );
        } catch (RuntimeException $runtimeException) {
            if ((int) $runtimeException->getCode() === 404) {
                return $this->error('Invoice not found.', 404);
            }

            return $this->error('Failed to fetch invoice PDF.', (int) $runtimeException->getCode() >= 400 ? (int) $runtimeException->getCode() : 500);
        } catch (Throwable $throwable) {
            return $this->error('Failed to fetch invoice PDF.', 500);
        }
    }
}
