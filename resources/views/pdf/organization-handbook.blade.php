<!DOCTYPE html>
<html lang="en">
@php
    $inlineFont = static function (string $baseName): ?string {
        $base64Path = public_path("fonts/{$baseName}.b64");

        if (is_file($base64Path)) {
            $contents = trim((string) file_get_contents($base64Path));

            if ($contents !== '') {
                return 'data:font/truetype;base64,'.$contents;
            }
        }

        $ttfPath = public_path("fonts/{$baseName}.ttf");

        if (is_file($ttfPath)) {
            $contents = file_get_contents($ttfPath);

            if ($contents !== false && $contents !== '') {
                return 'data:font/truetype;base64,'.base64_encode($contents);
            }
        }

        return null;
    };

    $inter400Font = $inlineFont('inter-400');
    $inter600Font = $inlineFont('inter-600');
    $inter700Font = $inlineFont('inter-700');
@endphp
<head>
    <meta charset="utf-8">
    <title>{{ $document->metadata['title'] }}</title>
    <meta name="author" content="{{ $document->metadata['author'] }}">
    <meta name="creator" content="{{ $document->metadata['creator'] }}">
    <meta name="subject" content="{{ $document->metadata['subject'] }}">
    <meta name="generated-at" content="{{ $document->metadata['generated_at'] }}">
    <style>
        /* --- Inter font (base64-embedded for offline PDF) --- */
        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 400;
            font-display: swap;
            src:
                local('Inter Regular'),
                local('Inter'),
                @if ($inter400Font)
                url('{{ $inter400Font }}') format('truetype');
                @else
                local('Helvetica');
                @endif
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 600;
            font-display: swap;
            src:
                local('Inter SemiBold'),
                local('Inter'),
                @if ($inter600Font)
                url('{{ $inter600Font }}') format('truetype');
                @else
                local('Helvetica');
                @endif
        }

        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 700;
            font-display: swap;
            src:
                local('Inter Bold'),
                local('Inter'),
                @if ($inter700Font)
                url('{{ $inter700Font }}') format('truetype');
                @else
                local('Helvetica');
                @endif
        }

        /* ============================================================
           Metric Harmony - Executive Handbook v3
           ============================================================ */
        :root {
            --ink: #111827;
            --ink-soft: #374151;
            --gray: #6b7280;
            --silver: #9ca3af;
            --light: #f3f4f6;
            --lighter: #f9fafb;
            --rule: #e5e7eb;
            --white: #ffffff;
            --brand: #0088c7;
            --brand-dark: #0f3d56;
            --brand-light: #e9f7ff;
            --brand-border: #33aee8;
            --green-bg: #f0fdf4;
            --green-fg: #166534;
            --green-bd: #86efac;
            --amber-bg: #fffbeb;
            --amber-fg: #92400e;
            --amber-bd: #fcd34d;
        }

        @page {
            size: A4;
            margin: 10mm 14mm 16mm;

            @bottom-left {
                content: "Metrics Handbook | Confidential";
                color: var(--silver);
                font-family: 'Inter', Helvetica, Arial, sans-serif;
                font-size: 7px;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }

            @bottom-right {
                content: counter(page);
                color: var(--brand);
                font-family: 'Inter', Helvetica, Arial, sans-serif;
                font-size: 8px;
                font-weight: 700;
            }
        }

        @page :first {
            margin: 0;

            @bottom-left {
                content: none;
            }

            @bottom-right {
                content: none;
            }
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            color: var(--ink);
            font-family: 'Inter', Helvetica, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            background: var(--white);
            overflow-wrap: break-word;
            word-break: normal;
            -webkit-font-smoothing: antialiased;
        }

        main {
            display: block;
            max-width: 820px;
            margin: 0 auto;
            padding: 0 4px;
        }

        /* ---------- Headings ---------- */
        h1,
        h2,
        h3,
        h4 {
            margin: 0 0 10px;
            color: var(--ink);
            line-height: 1.2;
            font-weight: 700;
        }

        h1 {
            font-size: 36px;
            letter-spacing: 0;
            font-weight: 700;
        }

        h2 {
            color: var(--ink);
            font-size: 15px;
            margin-top: 22px;
            padding-bottom: 6px;
            padding-left: 12px;
            border-bottom: none;
            border-left: 4px solid var(--brand);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        h3 {
            color: var(--ink);
            font-size: 15px;
        }

        h4 {
            font-size: 12px;
        }

        p {
            margin: 0 0 6px;
        }

        .lede {
            color: var(--gray);
            font-size: 12.5px;
            margin-bottom: 10px;
            line-height: 1.5;
        }

        a {
            color: var(--brand);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        /* ---------- Cover Page ---------- */
        .cover-page {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: flex-start;
            min-height: 100vh;
            text-align: left;
            page-break-after: always;
            position: relative;
            padding: 60px 48px 80px;
            background: var(--white);
        }

        .cover-page::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background: var(--brand);
        }

        .cover-page__brand {
            color: var(--brand);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            margin-bottom: 24px;
            position: absolute;
            top: 36px;
            left: 48px;
        }

        .cover-page__title {
            color: var(--ink);
            font-size: 52px;
            font-weight: 700;
            letter-spacing: 0;
            margin-bottom: 6px;
            line-height: 1;
        }

        .cover-page__engagement {
            color: var(--gray);
            font-size: 20px;
            font-weight: 400;
            margin-bottom: 40px;
            letter-spacing: 0;
        }

        .cover-page__meta {
            display: grid;
            grid-template-columns: repeat(2, auto);
            gap: 2px 28px;
            text-align: left;
            font-size: 12px;
            margin-bottom: 0;
            padding-top: 16px;
            border-top: 1px solid var(--rule);
        }

        .cover-page__meta dt {
            color: var(--silver);
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .cover-page__meta dd {
            margin: 0 0 10px;
            color: var(--ink);
            font-size: 13px;
            font-weight: 600;
        }

        .cover-page__confidential {
            color: var(--brand);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.14em;
            position: absolute;
            bottom: 36px;
            right: 48px;
            text-transform: uppercase;
        }

        /* ---------- Front Matter ---------- */
        .front-matter {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin: 12px 0 0;
        }

        .front-matter__item {
            background: var(--lighter);
            border: 1px solid var(--rule);
            border-left: 3px solid var(--brand);
            padding: 10px 12px;
            border-radius: 0;
        }

        .front-matter__label,
        .summary-grid dt,
        .field-list dt,
        .signal-list dt {
            color: var(--silver);
            font-size: 9px;
            font-weight: 700;
            margin: 0 0 3px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .front-matter__value,
        .summary-grid dd,
        .field-list dd,
        .signal-list dd {
            margin: 0;
            color: var(--ink);
            font-weight: 500;
        }

        .executive-note {
            margin-top: 12px;
            padding: 10px 14px;
            border: none;
            border-top: 2px solid var(--brand);
            background: var(--lighter);
        }

        .executive-note p {
            margin: 0;
            color: var(--gray);
            font-size: 10.5px;
            line-height: 1.5;
        }

        .executive-table-wrap {
            margin-top: 10px;
            border: none;
            border-radius: 0;
            overflow: hidden;
        }

        .executive-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 11px;
        }

        .executive-table col.executive-table__col--metric {
            width: 20%;
        }

        .executive-table col.executive-table__col--status {
            width: 18%;
        }

        .executive-table col.executive-table__col--owner {
            width: 16%;
        }

        .executive-table col.executive-table__col--next-step {
            width: 36%;
        }

        .executive-table col.executive-table__col--version {
            width: 10%;
        }

        .executive-table thead {
            display: table-header-group;
        }

        .executive-table th,
        .executive-table td {
            padding: 7px 9px;
            text-align: left;
            vertical-align: top;
        }

        .executive-table th {
            background: var(--ink);
            color: var(--white);
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .executive-table tbody tr {
            page-break-inside: avoid;
        }

        .executive-table tbody td {
            border-top: 1px solid var(--rule);
            color: var(--ink);
            font-weight: 500;
            line-height: 1.5;
        }

        .executive-table tbody tr:hover {
            background: var(--lighter);
        }

        .executive-table__metric,
        .executive-table__version {
            font-weight: 700;
        }

        .executive-table__metric-link {
            color: var(--brand);
            font-weight: 700;
            text-decoration: none;
        }

        .executive-table__metric-link:hover {
            text-decoration: underline;
        }

        .executive-table__status .status-badge {
            font-size: 9px;
        }

        /* ---------- Section ---------- */
        section {
            margin-top: 18px;
        }

        /* ---------- Summary Grid ---------- */
        .summary-grid,
        .field-list,
        .signal-list {
            display: grid;
            gap: 8px;
            margin: 0;
        }

        .summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            background: var(--lighter);
            padding: 10px;
            border: 1px solid var(--rule);
            border-radius: 0;
        }

        .summary-grid>div {
            padding: 5px 8px;
            background: var(--white);
            border: 1px solid var(--rule);
            border-radius: 0;
        }

        .field-list,
        .signal-list {
            grid-template-columns: 1fr;
        }

        /* ---------- Metric Definitions Section ---------- */
        section[aria-labelledby="metric-sections"] {
            page-break-before: always;
        }

        /* ---------- Metric Card ---------- */
        .metric-card {
            margin-top: 20px;
            page-break-before: always;
            page-break-inside: auto;
        }

        .metric-card:first-of-type {
            page-break-before: avoid;
            margin-top: 14px;
        }

        .metric-card__snapshot {
            border: 1px solid var(--rule);
            border-top: 3px solid var(--brand);
            border-radius: 0;
            padding: 14px;
            background: var(--lighter);
            page-break-inside: avoid;
            margin-bottom: 14px;
        }

        .metric-card__header {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--ink);
            page-break-after: avoid;
        }

        .metric-card__header--no-takeaway {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 12px;
        }

        .metric-card__title-block {
            min-width: 0;
            flex: 1 1 auto;
        }

        .metric-card__title-block h3 {
            color: var(--ink);
            font-size: 19px;
            letter-spacing: 0;
            margin-bottom: 0;
        }

        .metric-card__baseline-label {
            flex: 0 0 auto;
            text-align: right;
        }

        .metric-card__eyebrow {
            color: var(--brand);
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.12em;
            margin: 0;
            text-transform: uppercase;
            background: var(--brand-light);
            border: 1px solid var(--brand-border);
            display: inline-block;
            padding: 3px 10px;
            border-radius: 0;
            white-space: nowrap;
        }

        .metric-card__approval-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 0;
            margin: 4px 0 0;
            color: var(--gray);
            font-size: 10px;
            font-weight: 500;
            line-height: 1.35;
        }

        .metric-card__approval-meta span + span::before {
            content: "-";
            color: var(--rule);
            margin: 0 6px;
            font-weight: 400;
        }

        .metric-card__headline {
            color: var(--gray);
            font-size: 11.5px;
            line-height: 1.55;
            margin: 0 0 10px;
            max-width: 90%;
            font-style: italic;
        }

        /* ---------- Summary Panels ---------- */
        .metric-card__summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 0;
            page-break-inside: auto;
        }

        .metric-card__summary-panel {
            background: var(--white);
            border: 1px solid var(--rule);
            border-radius: 0;
            padding: 10px 12px;
            page-break-inside: avoid;
        }

        .metric-card__summary-panel--wide {
            grid-column: 1 / -1;
        }

        .metric-card__summary-panel h4 {
            margin-top: 0;
            font-size: 9px;
            font-weight: 700;
            color: var(--brand);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding-bottom: 4px;
            border-bottom: 1px solid var(--rule);
            margin-bottom: 6px;
        }

        .metric-card__summary-panel .lede {
            font-size: 10.5px;
            color: var(--silver);
            font-style: italic;
            margin-bottom: 8px;
            line-height: 1.45;
        }

        .metric-card__meta p:last-child,
        .metric-card p:last-child {
            margin-bottom: 0;
        }

        /* ---------- Status Badges ---------- */
        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 2px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .status-badge--ready {
            background: var(--green-bg);
            color: var(--green-fg);
            border: 1px solid var(--green-bd);
        }

        .status-badge--follow-up {
            background: var(--amber-bg);
            color: var(--amber-fg);
            border: 1px solid var(--amber-bd);
        }

        /* ---------- Field / Signal Lists ---------- */
        .field-list article,
        .signal-list article,
        .companion-list article {
            border-top: 1px solid var(--rule);
            padding-top: 6px;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .field-list article:first-child,
        .signal-list article:first-child,
        .companion-list article:first-child {
            border-top: 0;
            padding-top: 0;
        }

        .field-list dt,
        .signal-list dt {
            margin-top: 4px;
        }

        .field-list article:first-child dt,
        .signal-list article:first-child dt {
            margin-top: 0;
        }

        .companion-list {
            display: grid;
            gap: 8px;
            margin-top: 6px;
            padding-left: 12px;
        }

        /* ---------- Executive Section Titles ---------- */
        .executive-section-title {
            color: var(--ink);
            font-size: 10px;
            font-weight: 700;
            margin: 14px 0 6px;
            padding-bottom: 4px;
            padding-left: 10px;
            border-bottom: none;
            border-left: 3px solid var(--brand);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            break-after: avoid;
            page-break-after: avoid;
        }

        .executive-section-title + .field-list {
            break-before: avoid;
            page-break-before: avoid;
        }

        .metric-detail-section--compact {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        /* ---------- Appendix ---------- */
        .appendix-section {
            margin-top: 22px;
        }

        .appendix-section--start {
            page-break-before: always;
        }

        .appendix-section h2 {
            page-break-after: avoid;
        }

        .appendix-table-wrap {
            border: none;
            border-radius: 0;
            overflow: hidden;
        }

        .appendix-table {
            width: 100%;
            border-collapse: collapse;
        }

        .appendix-table thead {
            display: table-header-group;
        }

        .appendix-table th,
        .appendix-table td {
            padding: 7px 10px;
            text-align: left;
            vertical-align: top;
        }

        .appendix-table th {
            background: var(--ink);
            color: var(--white);
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .appendix-table tbody tr {
            page-break-inside: avoid;
        }

        .appendix-table tbody tr:nth-child(even) {
            background: var(--lighter);
        }

        .appendix-table tbody td {
            border-top: 1px solid var(--rule);
            color: var(--ink);
            font-weight: 500;
        }

        .appendix-table__metric {
            font-weight: 700;
        }

        .empty-state {
            border: 1px dashed var(--rule);
            padding: 14px;
            color: var(--gray);
            border-radius: 0;
            text-align: center;
            font-style: italic;
        }

        /* ---------- Notes ---------- */
        section[aria-labelledby^="notes-"] {
            margin-top: 10px;
            padding: 8px 12px;
            background: var(--brand-light);
            border-left: 3px solid var(--brand);
            border-radius: 0;
            page-break-inside: avoid;
        }

        section[aria-labelledby^="notes-"] h4 {
            color: var(--brand-dark);
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-top: 0;
            margin-bottom: 4px;
        }

        section[aria-labelledby^="notes-"] p {
            margin-bottom: 0;
            color: var(--ink-soft);
            font-size: 11.5px;
        }

        /* ---------- Print ---------- */
        @media print {
            body {
                font-size: 12px;
            }

            main {
                max-width: none;
                padding: 0;
            }

            .cover-page {
                min-height: 98vh;
            }

            .metric-card__snapshot,
            .metric-card__summary-panel,
            .summary-grid,
            .executive-table-wrap,
            .appendix-table-wrap,
            .appendix-table tbody tr:nth-child(even),
            .front-matter__item,
            .metric-card__eyebrow,
            .status-badge,
            .cover-page::before,
            section[aria-labelledby^="notes-"],
            .executive-note,
            .executive-table th,
            .appendix-table th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            h2 {
                margin-top: 18px;
            }

            p, dd, td {
                orphans: 2;
                widows: 2;
            }
        }

        /* ---------- Screen ---------- */
        @media screen {
            body {
                background: #f3f4f6;
                padding: 40px 20px;
            }

            main {
                background: var(--white);
                padding: 0 48px 60px;
                border-radius: 0;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05), 0 4px 20px rgba(0, 0, 0, 0.06);
                border-top: 4px solid var(--brand);
            }

            .cover-page {
                min-height: 80vh;
            }
        }

    </style>
</head>
<body>
    <main>
        {{-- ===== COVER PAGE ===== --}}
        <div class="cover-page">
            <p class="cover-page__brand">Metric Harmony</p>
            <h1 class="cover-page__title">Metrics Handbook</h1>
            <p class="cover-page__engagement">{{ $document->executiveBrief['engagement_title'] ?? 'Executive Metric Baseline' }}</p>
            <dl class="cover-page__meta">
                <dt>Organization</dt>
                <dd>{{ $document->organizationName }}</dd>
                <dt>Generated</dt>
                <dd>{{ \Carbon\Carbon::parse($document->generatedAt)->format('F j, Y') }}</dd>
                @if ($document->executiveBrief['date_range'])
                <dt>Engagement Period</dt>
                <dd>{{ $document->executiveBrief['date_range'] }}</dd>
                @endif
                @if ($document->executiveBrief['next_review_on'])
                <dt>Next Review</dt>
                <dd>{{ $document->executiveBrief['next_review_on'] }}</dd>
                @endif
                @if ($document->executiveBrief['review_cadence'])
                <dt>Cadence</dt>
                <dd>{{ $document->executiveBrief['review_cadence'] }}</dd>
                @endif
            </dl>
            <p class="cover-page__confidential">Confidential</p>
        </div>

        {{-- ===== EXECUTIVE METRIC SNAPSHOT ===== --}}
        <section aria-labelledby="executive-metric-snapshot">
            @if ($document->executiveMetricRows !== [])
            <h2 id="executive-metric-snapshot">Executive Metric Snapshot</h2>
            <div class="executive-table-wrap">
                <table class="executive-table" aria-labelledby="executive-metric-snapshot">
                    <colgroup>
                        <col class="executive-table__col--metric">
                        <col class="executive-table__col--status">
                        <col class="executive-table__col--owner">
                        <col class="executive-table__col--next-step">
                        <col class="executive-table__col--version">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Metric</th>
                            <th scope="col">Status</th>
                            <th scope="col">Metric Owner</th>
                            <th scope="col">Next Step</th>
                            <th scope="col">Version</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($document->executiveMetricRows as $metricRow)
                        <tr>
                            <td class="executive-table__metric">
                                <a class="executive-table__metric-link" href="#metric-{{ $loop->index }}">{{ $metricRow['metric_name'] }}</a>
                            </td>
                            <td class="executive-table__status">
                                @if ($metricRow['status_tone'] === 'ready')
                                <span class="status-badge status-badge--ready">{{ $metricRow['status_label'] }}</span>
                                @elseif ($metricRow['status_tone'] === 'follow-up')
                                <span class="status-badge status-badge--follow-up">Follow-up Needed</span>
                                @else
                                {{ $metricRow['status_label'] }}
                                @endif
                            </td>
                            <td>{{ $metricRow['owner'] }}</td>
                            <td>{{ $metricRow['next_step'] }}</td>
                            <td class="executive-table__version">{{ $metricRow['version'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @if ($document->metrics !== [])
            <aside class="executive-note" aria-label="handbook-provenance">
                <p>Every definition in this handbook is a decision made and approved by the named {{ $document->organizationName }} stakeholders during a facilitated Metric Harmony engagement. Metric Harmony records and renders those signed decisions; it does not generate definition content.</p>
            </aside>
            @endif

            @if (collect($document->metrics)->contains(fn (array $metric): bool => $metric['operational_status_label'] || $metric['operational_status_notes']))
            <aside class="executive-note" aria-label="implementation-status-boundary">
                <p>Implementation status reflects the organization's confirmed operating state and does not represent independent validation of source data or dashboard outputs by Metric Harmony.</p>
            </aside>
            @endif
        </section>

        {{-- ===== METRIC DEFINITIONS ===== --}}
        <section aria-labelledby="metric-sections">
            <h2 id="metric-sections">Metric Definitions</h2>

            @forelse ($document->metrics as $metric)
            <article class="metric-card" aria-labelledby="metric-{{ $loop->index }}">
                <header @class(['metric-card__header', 'metric-card__header--no-takeaway' => !$metric['executive_takeaway']])>
                    <div class="metric-card__title-block">
                        <h3 id="metric-{{ $loop->index }}">{{ $metric['name'] }}</h3>
                        @if ($metric['approval_strip_segments'] !== [])
                        <p class="metric-card__approval-meta">
                            @foreach ($metric['approval_strip_segments'] as $segment)
                            <span>{{ $segment }}</span>
                            @endforeach
                        </p>
                        @endif
                    </div>
                    <div class="metric-card__baseline-label">
                        <p class="metric-card__eyebrow">Approved Metric Baseline</p>
                    </div>
                </header>

                @if ($metric['executive_takeaway'])
                <p class="metric-card__headline">{{ $metric['executive_takeaway'] }}</p>
                @endif

                <div class="metric-card__snapshot">
                    <div class="metric-card__summary">
                        @if ($metric['alignment_summary'])
                        <section class="metric-card__summary-panel" aria-labelledby="alignment-summary-{{ $loop->index }}">
                            <h4 id="alignment-summary-{{ $loop->index }}">Executive Alignment</h4>
                            <p>{{ $metric['alignment_summary'] }}</p>
                        </section>
                        @endif

                        @if ($metric['operational_status_label'] || $metric['operational_status_notes'])
                        <section class="metric-card__summary-panel" aria-labelledby="operational-status-{{ $loop->index }}">
                            <h4 id="operational-status-{{ $loop->index }}">Implementation Status</h4>
                            <dl class="signal-list">
                                @if ($metric['operational_status_label'])
                                <div>
                                    <dt>Status</dt>
                                    <dd>
                                        @if (str_contains($metric['operational_status_label'], 'Ready'))
                                        <span class="status-badge status-badge--ready">{{ $metric['operational_status_label'] }}</span>
                                        @else
                                        <span class="status-badge status-badge--follow-up">{{ $metric['operational_status_label'] }}</span>
                                        @endif
                                    </dd>
                                </div>
                                @endif
                                @if ($metric['operational_status_notes'])
                                <div>
                                    <dt>Notes</dt>
                                    <dd>{{ $metric['operational_status_notes'] }}</dd>
                                </div>
                                @endif
                            </dl>
                        </section>
                        @endif

                        @if ($metric['implementation_gap_rows'] !== [])
                        <section class="metric-card__summary-panel metric-card__summary-panel--wide" aria-labelledby="implementation-gap-{{ $loop->index }}">
                            <h4 id="implementation-gap-{{ $loop->index }}">Implementation Follow-up</h4>
                            <dl class="signal-list">
                                @foreach ($metric['implementation_gap_rows'] as $row)
                                <div>
                                    <dt>{{ $row['label'] }}</dt>
                                    <dd>{{ $row['value'] }}</dd>
                                </div>
                                @endforeach
                            </dl>
                        </section>
                        @endif

                    </div>
                </div>

                @foreach ($metric['executive_sections'] as $section)
                <section class="metric-detail-section {{ count($section['items']) <= 3 ? 'metric-detail-section--compact' : '' }}" aria-labelledby="section-{{ $loop->parent->index }}-{{ $loop->index }}">
                    <h4 id="section-{{ $loop->parent->index }}-{{ $loop->index }}" class="executive-section-title">{{ $section['title'] }}</h4>
                    <div class="field-list">
                        @foreach ($section['items'] as $field)
                        <article>
                            <dl>
                                <dt>{{ $field['label'] }}</dt>
                                <dd>{{ $field['value'] }}</dd>
                                @if (($section['title'] ?? null) === 'Official Definition' && filled($field['rationale'] ?? null))
                                <dt>Decision rationale</dt>
                                <dd>{{ $field['rationale'] }}</dd>
                                @endif
                            </dl>

                            @if ($field['companions'] !== [])
                            <div class="companion-list">
                                @foreach ($field['companions'] as $companion)
                                <article>
                                    <dl>
                                        <dt>{{ $companion['label'] }}</dt>
                                        <dd>{{ $companion['value'] }}</dd>
                                    </dl>
                                </article>
                                @endforeach
                            </div>
                            @endif
                        </article>
                        @endforeach
                    </div>
                </section>
                @endforeach

                @if ($metric['validation_guidance_rows'] !== [])
                <section class="metric-detail-section metric-detail-section--compact" aria-labelledby="validation-guidance-{{ $loop->index }}">
                    <h4 id="validation-guidance-{{ $loop->index }}" class="executive-section-title">Operational Validation Checklist</h4>
                    <div class="field-list">
                        @foreach ($metric['validation_guidance_rows'] as $row)
                        <article>
                            <dl>
                                <dt>{{ $row['label'] }}</dt>
                                <dd>{{ $row['value'] }}</dd>
                            </dl>
                        </article>
                        @endforeach
                    </div>
                </section>
                @endif

                <section aria-labelledby="notes-{{ $loop->index }}">
                    <h4 id="notes-{{ $loop->index }}">Notes</h4>
                    <p>{{ $metric['notes'] }}</p>
                </section>
            </article>
            @empty
            <div class="empty-state">
                <p>No finalized metrics were available when this handbook was generated.</p>
            </div>
            @endforelse
        </section>

        {{-- ===== APPROVAL SUMMARY ===== --}}
        @if ($document->approvals !== [])
        <section class="appendix-section appendix-section--start" aria-labelledby="approval-summary">
            <h2 id="approval-summary">Approval Summary</h2>
            <div class="appendix-table-wrap">
                <table class="appendix-table">
                    <thead>
                        <tr>
                            <th scope="col">Metric</th>
                            <th scope="col">Approved on</th>
                            <th scope="col">Approved by</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($document->approvals as $approval)
                        <tr>
                            <td class="appendix-table__metric">{{ $approval['metric_name'] }}</td>
                            <td>{{ \Carbon\Carbon::parse($approval['signed_off_at'])->format('F j, Y') }}</td>
                            <td>{{ $approval['signed_off_by_name'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endif

        {{-- ===== REVISION HISTORY ===== --}}
        @php
            $revisionHistorySectionClass = $document->approvals === []
                ? 'appendix-section appendix-section--start'
                : 'appendix-section';
        @endphp
        @if ($document->revisionHistory !== [])
        <section class="{{ $revisionHistorySectionClass }}" aria-labelledby="revision-history-appendix">
            <h2 id="revision-history-appendix">Revision History Appendix</h2>
            <div class="appendix-table-wrap">
                <table class="appendix-table">
                    <thead>
                        <tr>
                            <th scope="col">Metric</th>
                            <th scope="col">Version</th>
                            <th scope="col">Approved on</th>
                            <th scope="col">Approved by</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($document->revisionHistory as $metricHistory)
                        @foreach ($metricHistory['history'] as $historyEntry)
                        <tr>
                            <td class="appendix-table__metric">{{ $metricHistory['name'] }}</td>
                            <td>{{ $historyEntry['version'] }}</td>
                            <td>{{ \Carbon\Carbon::parse($historyEntry['approved_on'])->format('F j, Y') }}</td>
                            <td>{{ $historyEntry['approved_by'] }}</td>
                        </tr>
                        @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endif
    </main>
</body>
</html>
