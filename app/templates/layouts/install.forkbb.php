<!DOCTYPE html>
<html lang="{{ __('lang_identifier') }}" dir="{{ __('lang_direction') }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{!! __('ForkBB Installation') !!}</title>
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
    <header id="fork-header">
      <h1 id="id-fhth1"><span id="id-fhtha">{!! __('ForkBB Installation') !!}</span></h1>
      <p id="id-fhtdesc">{!! __('Welcome') !!}</p>
    </header>
    <main>
@if ($iswev = $p->fIswev)
    @include ('layouts/iswev')
@endif
@if ($form = $p->form1)
    <section class="f-install">
      <h2>{!! __('Choose install language') !!}</h2>
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
    </section>
@endif
@if (! $p->fIswev['e'])
    @if ($form = $p->form2)
    <section class="f-install">
      <h2>{!! __(['Install', $p->rev]) !!}</h2>
      <div class="f-fdiv">
        @include ('layouts/form')
      </div>
    </section>
    @endif
@endif
    </main>
<!-- debuginfo -->
  </div>
</body>
</html>
