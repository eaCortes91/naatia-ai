<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #173845; font-size: 13px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        .muted { color: #4b6974; font-size: 11px; }
        .card { border: 1px solid #d5e4e3; border-radius: 8px; padding: 12px; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #e5efee; }
        .total { font-size: 18px; font-weight: bold; color: #173845; }
    </style>
</head>
<body>
    <h1>Cotización de reservación</h1>
    <div class="muted">Generado: {{ $generatedAt->format('d/m/Y H:i') }}</div>

    <div class="card">
        <strong>Hotel:</strong> {{ $hotel->nombre }}<br>
        @if($hotel->telefono)<strong>Tel:</strong> {{ $hotel->telefono }}<br>@endif
        @if($hotel->email)<strong>Email:</strong> {{ $hotel->email }}<br>@endif
        @if($hotel->address_line)<strong>Dirección:</strong> {{ $hotel->address_line }}@if($hotel->city), {{ $hotel->city }}@endif<br>@endif
    </div>

    <div class="card">
        <table>
            <tr><th>Huésped</th><td>{{ $contact?->nombre ?? 'Cliente' }}</td></tr>
            <tr><th>Habitación</th><td>{{ $room->nombre }}</td></tr>
            <tr><th>Check-in</th><td>{{ optional($reservation->check_in)->format('d/m/Y') }}</td></tr>
            <tr><th>Check-out</th><td>{{ optional($reservation->check_out)->format('d/m/Y') }}</td></tr>
            <tr><th>Noches</th><td>{{ $reservation->nights }}</td></tr>
            <tr><th>Huéspedes</th><td>{{ $reservation->guests }}</td></tr>
            <tr><th>Moneda</th><td>{{ $reservation->currency }}</td></tr>
            <tr><th>Total</th><td class="total">${{ number_format((float) $reservation->total_amount, 2) }} {{ $reservation->currency }}</td></tr>
        </table>
    </div>

    <div class="card muted">
        Vigencia sugerida de esta cotización: 24 horas. Sujeto a disponibilidad final al confirmar.
    </div>
</body>
</html>
