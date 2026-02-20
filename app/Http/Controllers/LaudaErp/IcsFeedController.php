<?php

namespace App\Http\Controllers\LaudaErp\Calendar;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IcsFeedController extends Controller
{
    public function show(Request $request, Company $company, string $token)
    {
        $tokenHash = hash('sha256', $token);

        $feed = DB::table('calendar_feeds')
            ->where('company_id', $company->id)
            ->where('enabled', true)
            ->where('token_hash', $tokenHash)
            ->first();

        abort_unless($feed, 404);

        $tz = $company->timezone ?? 'UTC';
        $now = CarbonImmutable::now($tz);

        $from = $now->subDays(30)->toDateString();
        $to   = $now->addDays(365)->toDateString();

        $items = DB::table('obligation_instances as oi')
            ->join('tenant_obligations as to', 'to.id', '=', 'oi.tenant_obligation_id')
            ->join('compliance_obligation_templates as t', 't.id', '=', 'to.template_id')
            ->join('tax_authorities as a', 'a.id', '=', 't.authority_id')
            ->where('oi.company_id', $company->id)
            ->whereBetween('oi.due_date', [$from, $to])
            ->orderBy('oi.due_date')
            ->select([
                'oi.id',
                'oi.period_key',
                'oi.due_date',
                'oi.status',
                't.code as template_code',
                't.name as template_name',
                'a.code as authority_code',
                'a.name as authority_name',
            ])
            ->get();

        $ics = $this->renderIcs($company, $feed->label ?? 'Fiscal Compliance', $items);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="fiscal-compliance.ics"',
            'Cache-Control' => 'private, max-age=300', // 5 min
        ]);
    }

    private function renderIcs(Company $company, string $calName, $items): string
    {
        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//LaudaAPI//Compliance Calendar//ES';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';
        $lines[] = 'X-WR-CALNAME:' . $this->escape($calName);

        $appUrl = config('app.url');

        foreach ($items as $it) {
            $due = CarbonImmutable::parse($it->due_date);
            $dtStart = $due->format('Ymd');
            $dtEnd = $due->addDay()->format('Ymd'); // DTEND exclusivo

            $uid = "laudaapi-oi-{$it->id}@" . parse_url($appUrl, PHP_URL_HOST);

            $summary = "[{$it->authority_code}] {$it->template_code} - vence ({$it->period_key})";
            $desc = "{$it->authority_name}\n{$it->template_name}\nPeriodo: {$it->period_key}\nEstado: {$it->status}";

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $this->escape($uid);
            $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $lines[] = 'DTSTART;VALUE=DATE:' . $dtStart;
            $lines[] = 'DTEND;VALUE=DATE:' . $dtEnd;
            $lines[] = 'SUMMARY:' . $this->escape($summary);
            $lines[] = 'DESCRIPTION:' . $this->escape($desc);

            if ($appUrl) {
                // si luego tienes una página de detalle, cámbialo a esa ruta
                $lines[] = 'URL:' . $this->escape(rtrim($appUrl, '/'));
            }

            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private function escape(string $v): string
    {
        // ICS escaping básico
        $v = str_replace("\\", "\\\\", $v);
        $v = str_replace("\r\n", "\n", $v);
        $v = str_replace("\n", "\\n", $v);
        $v = str_replace(",", "\\,", $v);
        $v = str_replace(";", "\\;", $v);
        return $v;
    }
}
