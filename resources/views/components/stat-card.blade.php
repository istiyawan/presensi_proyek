@props(['label', 'value', 'icon', 'tone' => 'brand', 'foot' => null, 'hero' => false])
<div {{ $attributes->class(['stat-card', 'is-hero' => $hero]) }}>
    <div @class(['stat-icon', 'soft-'.$tone => ! $hero])><i class="bi bi-{{ $icon }}"></i></div>
    <div class="stat-label">{{ $label }}</div>
    <div class="stat-value">{{ $value }}</div>
    @if ($foot)<div class="stat-foot">{!! $foot !!}</div>@endif
</div>
