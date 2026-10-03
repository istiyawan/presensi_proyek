<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($title) ? $title.' · ' : '' }}{{ $appSettings['app_name'] ?? config('app.name') }}</title>
@if (! empty($appSettings['logo']))
    <link rel="icon" href="{{ route('branding.logo') }}">
@endif
<style>
    :root {
        --brand: {{ $appSettings['primary_color'] ?? '#1e5aa8' }};
        --brand-rgb: {{ \App\Support\Brand::rgb($appSettings['primary_color'] ?? null) }};
    }
</style>
