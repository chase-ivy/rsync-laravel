{{--
    Round profile picture, or the person's initials when there is no picture.
    @include('partials.avatar', ['u' => $user, 'size' => 40])
--}}
@php $size = $size ?? 40; $photo = $u?->photoUrl(); @endphp
<span class="avatar" style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ round($size * 0.38) }}px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;overflow:hidden;flex-shrink:0;background:#f3e2bf;color:#8a5a06;font-weight:700;vertical-align:middle;">
    @if ($photo)
        <img src="{{ $photo }}" alt="{{ $u->name }}" style="width:100%;height:100%;object-fit:cover;" onerror="this.remove()">
    @else
        {{ $u?->initials() ?? '?' }}
    @endif
</span>
