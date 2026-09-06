{{-- The PDF body. Dompdf supports a narrow slice of CSS, so the styles are
     inline and simple on purpose: no flexbox, no custom properties. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 18mm 12mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; color: #1f2430; }
        h1 { font-size: 13pt; margin: 0 0 2mm; }
        p.meta { margin: 0 0 5mm; font-size: 7pt; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.04em;
             color: #4b5563; border-bottom: 0.6pt solid #9ca3af; padding: 1.6mm 2mm; }
        td { border-bottom: 0.4pt solid #e5e7eb; padding: 1.4mm 2mm; vertical-align: top; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
<h1>{{ $title }}</h1>
<p class="meta">{{ $printedAt }} &middot; {{ count($rows) }}</p>
<table>
    <thead>
    <tr>
        @foreach ($headings as $heading)
            <th>{{ $heading }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach ($rows as $row)
        <tr>
            @foreach ($row as $cell)
                <td>{{ $cell }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
