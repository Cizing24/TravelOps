@props(['value'])
@if($value)
    <time datetime="{{ $value->toIso8601String() }}">{{ $value->copy()->setTimezone('Asia/Taipei')->format('Y/m/d H:i:s') }}</time>
@else
    <span>—</span>
@endif
