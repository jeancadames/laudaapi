<?php

namespace App\Http\Controllers\LaudaErp\Support;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SupportFaqItem;
use App\Models\SupportFaqVote;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ErpSupportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $company = $this->resolveCompanyForUser($user->id, $user->company_id, $user->subscriber_id);
        if (!$company) {
            return redirect()->route('erp.dashboard')
                ->with('error', 'No tienes empresa asignada todavía. Completa tu activación primero.');
        }

        $tickets = SupportTicket::query()
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get([
                'id',
                'number',
                'subject',
                'status',
                'priority',
                'channel',
                'last_reply_at',
                'created_at'
            ]);

        $faq = SupportFaqItem::query()
            ->with(['category:id,title,slug'])
            ->where('is_public', true)
            ->where('is_published', true)
            ->orderByDesc('view_count')
            ->limit(50)
            ->get([
                'id',
                'category_id',
                'slug',
                'question',
                'answer',
                'tags',
                'view_count',
                'helpful_up',
                'helpful_down'
            ]);

        return Inertia::render('LaudaERP/Support/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
            ],
            'tickets' => $tickets,
            'faq' => $faq,
        ]);
    }

    public function storeTicket(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $company = $this->resolveCompanyForUser($user->id, $user->company_id, $user->subscriber_id);
        if (!$company) return back()->with('error', 'No tienes empresa asignada todavía.');

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:20000'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'channel' => ['nullable', 'in:web,email,whatsapp,other'],
        ]);

        $subject = trim($data['subject']);
        $message = trim($data['message']);
        $priority = $data['priority'] ?? 'normal';
        $channel = $data['channel'] ?? 'web';

        try {
            $ticket = DB::transaction(function () use ($company, $user, $subject, $message, $priority, $channel) {
                $t = SupportTicket::create([
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'assigned_to_user_id' => null,
                    'number' => $this->nextTicketNumber(),
                    'subject' => $subject,
                    'status' => 'open',
                    'priority' => $priority,
                    'channel' => $channel,
                    'last_reply_at' => now(),
                    'first_response_at' => null,
                    'resolved_at' => null,
                    'meta' => null,
                ]);

                SupportTicketMessage::create([
                    'ticket_id' => $t->id,
                    'user_id' => $user->id,
                    'is_staff' => false,
                    'body' => $message,
                    'attachments' => null,
                    'meta' => null,
                ]);

                $t->last_reply_at = now();
                $t->save();

                return $t;
            });

            AuditService::log('erp_support_ticket_created', $ticket, [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->number,
            ], ['user_id' => $user->id]);

            return back()->with('success', 'Ticket creado. Te responderemos lo antes posible.');
        } catch (\Throwable $e) {
            report($e);

            AuditService::log('erp_support_ticket_create_failed', null, [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ], ['user_id' => $user->id]);

            return back()->with('error', 'No se pudo crear el ticket: ' . $e->getMessage());
        }
    }

    public function showTicket(Request $request, SupportTicket $ticket)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $company = $this->resolveCompanyForUser($user->id, $user->company_id, $user->subscriber_id);
        if (!$company) {
            return redirect()->route('erp.dashboard')->with('error', 'No tienes empresa asignada todavía.');
        }

        if ((int) $ticket->company_id !== (int) $company->id) abort(403);

        $messages = SupportTicketMessage::query()
            ->where('ticket_id', $ticket->id)
            ->orderBy('id')
            ->get(['id', 'user_id', 'is_staff', 'body', 'created_at']);

        return Inertia::render('LaudaERP/Support/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
            ],
            'ticket' => $ticket->only(['id', 'number', 'subject', 'status', 'priority', 'channel', 'created_at', 'last_reply_at']),
            'messages' => $messages,
        ]);
    }

    public function storeMessage(Request $request, SupportTicket $ticket)
    {
        $user = $request->user();
        if (!$user) abort(403);

        $company = $this->resolveCompanyForUser($user->id, $user->company_id, $user->subscriber_id);
        if (!$company) return back()->with('error', 'No tienes empresa asignada todavía.');

        if ((int) $ticket->company_id !== (int) $company->id) abort(403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
        ]);

        if ($ticket->status === 'closed') {
            return back()->with('error', 'Este ticket está cerrado.');
        }

        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'is_staff' => false,
            'body' => trim($data['body']),
            'attachments' => null,
            'meta' => null,
        ]);

        $ticket->last_reply_at = now();
        $ticket->status = 'pending';
        $ticket->save();

        AuditService::log('erp_support_ticket_message_created', $ticket, [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
        ], ['user_id' => $user->id]);

        return back()->with('success', 'Mensaje enviado.');
    }

    public function voteFaq(Request $request, SupportFaqItem $faqItem)
    {
        $user = $request->user();

        $data = $request->validate([
            'is_helpful' => ['required', 'boolean'],
        ]);

        SupportFaqVote::updateOrCreate(
            ['faq_item_id' => $faqItem->id, 'user_id' => $user?->id],
            [
                'is_helpful' => (bool) $data['is_helpful'],
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]
        );

        $up = SupportFaqVote::where('faq_item_id', $faqItem->id)->where('is_helpful', true)->count();
        $down = SupportFaqVote::where('faq_item_id', $faqItem->id)->where('is_helpful', false)->count();

        $faqItem->helpful_up = $up;
        $faqItem->helpful_down = $down;
        $faqItem->save();

        return back()->with('success', 'Gracias por tu feedback.');
    }

    private function nextTicketNumber(): string
    {
        $year = now()->format('Y');

        $last = SupportTicket::query()
            ->whereYear('created_at', now()->year)
            ->orderByDesc('id')
            ->value('number');

        $n = 1;
        if (is_string($last) && preg_match('/SUP-\d{4}-(\d+)/', $last, $m)) {
            $n = ((int) $m[1]) + 1;
        }

        return sprintf('SUP-%s-%06d', $year, $n);
    }

    private function resolveCompanyForUser(int $userId, $userCompanyId, $userSubscriberId): ?Company
    {
        if (!empty($userCompanyId)) {
            $c = Company::query()->find((int) $userCompanyId);
            if ($c) return $c;
        }

        $c = Company::query()->where('owner_user_id', $userId)->first();
        if ($c) return $c;

        $subscriberId = (int) DB::table('subscriber_user')
            ->where('user_id', $userId)
            ->where('active', 1)
            ->value('subscriber_id');

        if ($subscriberId <= 0 && !empty($userSubscriberId)) {
            $subscriberId = (int) $userSubscriberId;
        }

        if ($subscriberId > 0) {
            return Company::query()->where('subscriber_id', $subscriberId)->first();
        }

        return null;
    }
}
