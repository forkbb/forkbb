<!DOCTYPE html>
<html lang="{!! __('lang_identifier') !!}" dir="{!! __('lang_direction') !!}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <header class="f-header">
      <div class="f-title">
        <h1><a href="{!! $p->fRootLink !!}">{{ $p->fTitle }}</a></h1>
@if ($p->fDescription)
        <p class="f-description">{!! $p->fDescription !!}</p>
@endif
      </div>
@if ($p->fNavigation)
      <nav class="main-nav f-menu">
        <input id="main-nav-checkbox" style="display: none;" type="checkbox">
        <label class="f-menu-toggle" for="main-nav-checkbox"></label>
        <ul class="f-menu-items">
  @foreach ($p->fNavigation as $key => $val)
          <li><a id="nav-{{ $key }}" @if ($key == $p->fIndex) class="active" @endif href="{!! $val[0] !!}">{!! $val[1] !!}</a></li>
  @endforeach
        </ul>
      </nav>
@endif
    </header>
@if ($p->fAnnounce)
    <section class="f-announce">
      <h2>{!! __('Announcement') !!}</h2>
      <p class="f-ancontent">{!! $p->fAnnounce !!}</p>
    </section>
@endif
@if ($p->fIswev)
  @include ('layouts/iswev')
@endif
@yield ('content')
    <footer class="f-footer clearfix">
      <h2>{!! __('Board footer') !!}</h2>
      <div class="left">
      </div>
      <div class="right">
        <p class="poweredby">{!! __('Powered by') !!}</p>
      </div>
    </footer>
<!-- debuginfo -->
  </div>
</body>
</html>
