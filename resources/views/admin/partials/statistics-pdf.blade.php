<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 15px; margin: 0 0 8px; }
        .criteria { margin: 0 0 2px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: 1px solid #bfbfbf; padding: 5px 6px; text-align: left; }
        th { background: #2453d6; color: #ffffff; }
        .number { text-align: right; }
        .admin td { background: #eaf1ff; font-weight: bold; }
        .total td { background: #d1d5db; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    @foreach($criteria as $label => $value)
        <p class="criteria"><strong>{{ $label }} :</strong> {{ $value }}</p>
    @endforeach

    <table>
        <thead>
            <tr>
                @foreach($headers as $i => $header)
                    <th class="{{ $i >= 3 ? 'number' : '' }}">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr class="{{ $row['kind'] }}">
                    @foreach($row['cells'] as $i => $cell)
                        <td class="{{ $i >= 3 ? 'number' : '' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
