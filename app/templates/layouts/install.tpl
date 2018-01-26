<!DOCTYPE html>
<html lang="{!! __('lang_identifier') !!}" dir="{!! __('lang_direction') !!}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{!! __('ForkBB Installation') !!}</title>
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
    <header class="f-header">
      <div class="f-title">
        <h1>{!! __('ForkBB Installation') !!}</h1>
        <p class="f-description">{!! __('Welcome') !!}</p>
      </div>
    </header>
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
@if (empty($p->fIswev['e']))
  @if ($form = $p->form2)
    <section class="f-install">
      <h2>{!! __('Install', $p->rev) !!}</h2>
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
    </section>
  @endif
@endif
<!-- debuginfo -->
  </div>
</body>
</html>
