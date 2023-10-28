<!DOCTYPE html>
<html lang="{{ __('lang_identifier') }}" dir="{{ __('lang_direction') }}">
<head>
  <!-- PRE headStart -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="refresh" content="{{ $p->timeout }}; URL={{ $p->link }}">
  <title>{{! $p->pageTitle !}}</title>
@if ($p->mDescription)
  <meta name="description" content="{{! $p->mDescription !}}">
@endif
  <meta name="robots" content="noindex">
@foreach ($p->pageHeaders as $pageHeader)
    @if ('style' === $pageHeader['type'])
  <style>{!! $pageHeader['values'][0] !!}</style>
    @elseif ('script' !== $pageHeader['type'])
  <{{ $pageHeader['type'] }} @foreach ($pageHeader['values'] as $key => $val) {{ $key }}="{{ $val }}" @endforeach>
    @endif
@endforeach
  <!-- PRE headEnd -->
</head>
<body>
  <!-- PRE bodyStart -->
  <div id="fork">
    <!-- PRE mainBefore -->
    <main id="fork-main">
      <aside id="fork-rdrct" class="f-main">
        <h2 id="id-rdrct-h2">{!! __('Redirecting') !!}</h2>
@if ($iswev = $p->fIswev)
    @include ('layouts/iswev')
@endif
      </aside>
    </main>
    <!-- PRE mainAfter -->
    <!-- PRE footerBefore -->
    <footer id="fork-footer">
<!-- debuginfo -->
    </footer>
    <!-- PRE footerAfter -->
  </div>
  <!-- PRE bodyEnd -->
</body>
</html>
