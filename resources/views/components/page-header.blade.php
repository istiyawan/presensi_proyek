@props(['title', 'eyebrow' => null, 'subtitle' => null])
<div class="page-header">
    <div class="min-w-0">
        @if ($eyebrow)<div class="eyebrow">{{ $eyebrow }}</div>@endif
        <h1>{{ $title }}</h1>
        @if ($subtitle)<p>{!! $subtitle !!}</p>@endif
    </div>
    @isset($actions)
        <div class="actions">{{ $actions }}</div>
    @endisset
</div>
