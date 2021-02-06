<!DOCTYPE html>
<html lang="{!! __('lang_identifier') !!}" dir="{!! __('lang_direction') !!}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="refresh" content="{{ $p->timeout }};URL={{ $p->link }}">
  <title>{{ $p->pageTitle }}</title>
@foreach ($p->pageHeaders as $pageHeader)
    @if ('style' === $pageHeader['type'])
  <style>{!! $pageHeader['values'][0] !!}</style>
    @elseif ('script' !== $pageHeader['type'])
  <{{ $pageHeader['type'] }} @foreach ($pageHeader['values'] as $key => $val) {{ $key }}="{{ $val }}" @endforeach>
    @endif
@endforeach
</head>
<body>
  <div id="fork">
    <main>
      <section id="fork-rdrct" class="f-main">
        <h2>{!! __('Redirecting') !!}</h2>
        <p>{!! $p->message !!}</p>
        <p><a href="{{ $p->link }}">{!! __('Click redirect') !!}</a></p>
      </section>
    </main>
<!-- debuginfo -->
  </div>
</body>
</html>
