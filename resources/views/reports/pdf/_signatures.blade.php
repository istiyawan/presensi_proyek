{{-- Kolom tanda tangan: 1 orang = rata kanan (format acuan), 2–4 orang = dibagi rata --}}
@php $count = $footer['signatories']->count(); @endphp
<div class="sign">
    <table>
        <tr>
            @if ($count <= 1)
                <td style="width: 62%"></td>
            @endif
            @forelse ($footer['signatories'] as $i => $sig)
                <td style="width: {{ $count <= 1 ? 38 : round(100 / $count, 2) }}%">
                    {{-- Kota & tanggal di atas kolom paling kanan --}}
                    @if ($loop->last)
                        {{ $footer['city'] }}, {{ $footer['date'] }}<br>
                    @else
                        <br>
                    @endif
                    {{ $sig->label }}<br>
                    {!! nl2br(e($sig->organization)) !!}
                    <div class="space"></div>
                    <span class="name">{{ $sig->name }}</span><br>
                    <span class="title">{{ $sig->title }}</span>
                </td>
            @empty
                <td style="width: 38%">{{ $footer['city'] }}, {{ $footer['date'] }}</td>
            @endforelse
        </tr>
    </table>
</div>
