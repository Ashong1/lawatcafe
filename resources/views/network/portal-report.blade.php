@extends('layouts.admin')
@section('title', 'Sign-in Report')

@section('content')
@php
    $top = max(1, $funnel[0]['value']);
    $peak = max(1, max($byHour));
    $hourLabel = fn (int $h) => \Illuminate\Support\Carbon::createFromTime($h)->format('g A');
    $busiest = array_keys($byHour, max($byHour))[0] ?? null;
@endphp
<div class="max-w-6xl mx-auto space-y-6">

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#3E2723]">Sign-in Report</h1>
            <p class="text-sm text-[#6D4C41] mt-1">How guests use the Wi-Fi sign-in page. Counts are devices, not page loads.</p>
        </div>
        <nav class="inline-flex rounded-xl border border-[#E6D5C3] bg-white p-1" aria-label="Time range">
            @foreach([1 => 'Today', 7 => '7 days', 30 => '30 days'] as $d => $label)
                <a href="{{ route('network.portal-report', ['days' => $d]) }}" @if($days === $d) aria-current="page" @endif
                   class="min-h-[36px] px-3 inline-flex items-center rounded-lg text-sm font-semibold {{ $days === $d ? 'bg-[#3E2723] text-white' : 'text-[#5D4037] hover:bg-[#FAF7F2]' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    {{-- Headline numbers --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach([
            ['Codes used', $vouchersUsed, 'Codes that got a guest online'],
            ['Average plan', $avgMinutes ? ($avgMinutes >= 60 ? round($avgMinutes / 60, 1).' hr' : $avgMinutes.' min') : '—', 'Time bought per code'],
            ['Wrong codes', $wrongCodes, 'Typed codes that were refused'],
            ['Asked for more time', $moreTime, $timeAdded->count().' got time added by staff'],
        ] as [$label, $value, $hint])
            <div class="rounded-2xl border border-[#F0E6D2] bg-white p-4">
                <p class="text-sm font-semibold text-[#5D4037]">{{ $label }}</p>
                <p class="text-3xl font-bold text-[#3E2723] tabular-nums mt-1">{{ $value }}</p>
                <p class="text-xs text-[#6D4C41] mt-1">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- Funnel: one series, one hue, labels on the bars. --}}
        <section class="rounded-2xl border border-[#F0E6D2] bg-white p-5" aria-labelledby="funnel-title">
            <h2 id="funnel-title" class="text-base font-bold text-[#3E2723]">From opening the page to getting online</h2>
            <div class="mt-4 space-y-3">
                @foreach($funnel as $i => $step)
                    @php($pct = $funnel[0]['value'] ? round($step['value'] / $funnel[0]['value'] * 100) : 0)
                    <div>
                        <div class="flex justify-between text-sm">
                            <span class="font-semibold text-[#3E2723]">{{ $step['label'] }}</span>
                            <span class="tabular-nums text-[#5D4037]">{{ $step['value'] }} @if($i > 0)<span class="text-[#6D4C41]">({{ $pct }}%)</span>@endif</span>
                        </div>
                        <div class="mt-1 h-3 rounded-full bg-[#FAF7F2]" title="{{ $step['label'] }}: {{ $step['value'] }}">
                            <div class="h-3 rounded-full bg-[#3E2723]" style="width: {{ max($step['value'] ? 2 : 0, round($step['value'] / $top * 100)) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if($funnel[1]['value'] > $funnel[2]['value'])
                <p class="text-sm text-[#5D4037] mt-4">{{ $funnel[1]['value'] - $funnel[2]['value'] }} device(s) typed a code but never got online — see the wrong codes below.</p>
            @endif
        </section>

        {{-- Busiest hours: 24 bars on a shared baseline, hover for the value. --}}
        <section class="rounded-2xl border border-[#F0E6D2] bg-white p-5" aria-labelledby="hours-title">
            <h2 id="hours-title" class="text-base font-bold text-[#3E2723]">Guests getting online, by hour</h2>
            <p class="text-sm text-[#6D4C41]">
                @if(max($byHour) > 0) Busiest: {{ $hourLabel($busiest) }}. @else No connections in this period. @endif
            </p>
            <div class="mt-4 flex items-end gap-[2px] h-36 border-b border-[#E6D5C3]" role="img" aria-label="Connections by hour of day">
                @foreach($byHour as $h => $n)
                    <div class="group relative flex-1 h-full flex items-end">
                        <div class="w-full rounded-t-[4px] {{ $n ? 'bg-[#3E2723] group-hover:bg-amber-700' : '' }}" style="height: {{ $n ? max(3, round($n / $peak * 100)) : 0 }}%"></div>
                        <span class="pointer-events-none absolute -top-7 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-[#3E2723] px-2 py-0.5 text-xs text-white opacity-0 group-hover:opacity-100">{{ $hourLabel($h) }}: {{ $n }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-xs text-[#6D4C41]" aria-hidden="true">
                <span>12 AM</span><span>6 AM</span><span>12 PM</span><span>6 PM</span><span>11 PM</span>
            </div>
            <details class="mt-3 text-sm">
                <summary class="cursor-pointer font-semibold text-[#5D4037]">Show as a table</summary>
                <table class="mt-2 w-full text-sm tabular-nums">
                    @foreach($byHour as $h => $n)
                        @if($n)<tr><td class="py-0.5 text-[#5D4037]">{{ $hourLabel($h) }}</td><td class="text-right text-[#3E2723] font-semibold">{{ $n }}</td></tr>@endif
                    @endforeach
                </table>
            </details>
        </section>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <section class="rounded-2xl border border-[#F0E6D2] bg-white p-5">
            <h2 class="text-base font-bold text-[#3E2723]">Wrong codes</h2>
            @if($wrongCodes)
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach($failedByReason as $reason => $n)
                        <li class="flex justify-between"><span class="text-[#4A3B32]">{{ \App\Http\Controllers\PortalReportController::REASONS[$reason] ?? $reason }}</span><span class="font-semibold tabular-nums text-[#3E2723]">{{ $n }}</span></li>
                    @endforeach
                </ul>
                <table class="mt-4 w-full text-sm">
                    <thead><tr class="text-left text-xs text-[#6D4C41]"><th class="py-1 font-semibold">When</th><th class="font-semibold">Typed</th><th class="font-semibold">Why</th></tr></thead>
                    <tbody>
                        @foreach($recentFailures as $e)
                            <tr class="border-t border-[#FAF7F2]">
                                <td class="py-1.5 text-[#5D4037] whitespace-nowrap">{{ $e->created_at->timezone(config('app.timezone'))->format('M j, g:i A') }}</td>
                                <td class="font-mono text-[#3E2723]">{{ $e->voucher_code }}</td>
                                <td class="text-[#4A3B32]">{{ \App\Http\Controllers\PortalReportController::REASONS[$e->meta['reason'] ?? ''] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-sm text-[#5D4037] mt-2">No refused codes in this period.</p>
            @endif
        </section>

        <section class="rounded-2xl border border-[#F0E6D2] bg-white p-5">
            <h2 class="text-base font-bold text-[#3E2723]">Guests cut off early</h2>
            <p class="text-sm text-[#6D4C41]">A session that disappeared while its code still had time and nobody disconnected it. {{ $timeUp }} code(s) simply ran out of time.</p>
            @if($drops->isNotEmpty())
                @php($idle = $drops->pluck('meta.idle_minutes')->filter(fn ($m) => $m !== null))
                <p class="text-sm text-[#3E2723] mt-3">
                    <b>{{ $drops->count() }}</b> drop(s).
                    @if($idle->isNotEmpty()) Idle for a median of <b>{{ $idle->median() }} min</b> before the drop — if that's always about the same, it's the firewall's idle timeout. @endif
                </p>
                <table class="mt-3 w-full text-sm">
                    <thead><tr class="text-left text-xs text-[#6D4C41]"><th class="py-1 font-semibold">When</th><th class="font-semibold">Code</th><th class="font-semibold text-right">Idle</th><th class="font-semibold text-right">Time left</th></tr></thead>
                    <tbody>
                        @foreach($drops->take(10) as $e)
                            <tr class="border-t border-[#FAF7F2]">
                                <td class="py-1.5 text-[#5D4037] whitespace-nowrap">{{ $e->created_at->timezone(config('app.timezone'))->format('M j, g:i A') }}</td>
                                <td class="font-mono text-[#3E2723]">{{ $e->voucher_code }}</td>
                                <td class="text-right tabular-nums">{{ isset($e->meta['idle_minutes']) ? $e->meta['idle_minutes'].' min' : '—' }}</td>
                                <td class="text-right tabular-nums">{{ isset($e->meta['minutes_left']) ? $e->meta['minutes_left'].' min' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-sm text-[#5D4037] mt-2">No early drops recorded in this period.</p>
            @endif
        </section>
    </div>
</div>
@endsection
