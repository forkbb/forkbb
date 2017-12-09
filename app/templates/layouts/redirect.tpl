<!DOCTYPE html>
<html lang="{!! __('lang_identifier') !!}" dir="{!! __('lang_direction') !!}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="refresh" content="{!! $p->timeout !!};URL={{ $p->link }}">
  <title>{{ $p->pageTitle }}</title>
@foreach ($p->pageHeaders as $cur)
  @if ($cur[0] === 'style')
  <{!! $cur[0] !!}>{!! $cur[1] !!}</{!! $cur[0] !!}>
  @else
  <{!! $cur[0] !!} {!! $cur[1] !!}>
  @endif
@endforeach
</head>
<body>
  <div class="f-wrap">
    <section class="f-main f-redirect">
      <h2>{!! __('Redirecting') !!}</h2>
      <p>{!! $p->message !!}</p>
      <p><a href="{{ $p->link }}">{!! __('Click redirect') !!}</a></p>
    </section>
<!-- debuginfo -->
  </div>
</body>
</html>
