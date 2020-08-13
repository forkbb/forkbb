<!DOCTYPE html>
<html lang="{!! __('lang_identifier') !!}" dir="{!! __('lang_direction') !!}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $p->pageTitle }}</title>
@foreach ($p->pageHeaders as $cur)
    @if ('style' === $cur[0])
  <{!! $cur[0] !!}>{!! $cur[1] !!}</{!! $cur[0] !!}>
    @else
  <{!! $cur[0] !!} {!! $cur[1] !!}>
    @endif
@endforeach
</head>
<body>
  <div id="fork">
    <header id="fork-header">
      <div id="id-fhtitle">
        <h1 id="id-fhth1"><a id="id-fhtha" href="{!! $p->fRootLink !!}">{{ $p->fTitle }}</a></h1>
@if ('' != $p->fDescription)
        <p id="id-fhtdesc">{!! $p->fDescription !!}</p>
@endif
      </div>
@if ($p->fNavigation)
      <nav id="fork-nav" class="f-menu @if ($p->fNavigation['search']) f-main-nav-search @endif">
        <input id="id-mn-checkbox" class="f-menu-checkbox" type="checkbox">
        <label id="id-mn-label" class="f-menu-toggle" for="id-mn-checkbox"></label>
        <ul class="f-menu-items">
    @foreach ($p->fNavigation as $key => $val)
          <li id="id-nav-{!! $key !!}" class="f-menu-item"><!-- inline -->
            <a class="f-menu-a @if ($key == $p->fIndex) active @endif" href="{!! __($val[0]) !!}" @if ($val[2]) title="{!! __($val[2]) !!}" @endif><span>{!! __($val[1]) !!}</span></a>
        @if ($val[3])
            <ul class="f-submenu-items">
            @foreach ($val[3] as $key => $val)
              <li id="id-nav-{!! $key !!}" class="f-menu-item">
                @if ($val[0])
                <a class="f-menu-a @if ($key == $p->fSubIndex) active @endif" href="{!! __($val[0]) !!}" @if ($val[2]) title="{!! __($val[2]) !!}" @endif><span>{!! __($val[1]) !!}</span></a>
                @else
                <span class="f-menu-span">{!! __($val[1]) !!}</span>
                @endif
              </li>
            @endforeach
            </ul>
        @endif
          </li><!-- endinline -->
    @endforeach
        </ul>
    @if ($p->fNavigationUser)
        <ul class="f-menu-user-items">
        @foreach ($p->fNavigationUser as $key => $val)
          <li id="id-nav-{!! $key !!}" class="f-menu-item"><!-- inline -->
            <a class="f-menu-a @if ($key == $p->fIndex) active @endif" href="{!! __($val[0]) !!}" @if ($val[2]) title="{!! __($val[2]) !!}" @endif><span>{!! __($val[1]) !!}</span></a>
            @if ($val[3])
            <ul class="f-submenu-items">
                @foreach ($val[3] as $key => $val)
              <li id="id-nav-{!! $key !!}" class="f-menu-item">
                    @if ($val[0])
                <a class="f-menu-a @if ($key == $p->fSubIndex) active @endif" href="{!! __($val[0]) !!}" @if ($val[2]) title="{!! __($val[2]) !!}" @endif><span>{!! __($val[1]) !!}</span></a>
                    @else
                <span class="f-menu-span">{!! __($val[1]) !!}</span>
                    @endif
              </li>
                @endforeach
            </ul>
            @endif
          </li><!-- endinline -->
        @endforeach
        </ul>
    @endif
      </nav>
@endif
    </header>
@if ($p->fAnnounce)
    <section id="fork-announce">
      <h2>{!! __('Announcement') !!}</h2>
      <p id="id-facontent">{!! $p->fAnnounce !!}</p>
    </section>
@endif
@if ($iswev = $p->fIswev)
    @include ('layouts/iswev')
@endif
@yield ('content')
    <footer id="fork-footer">
      <h2>{!! __('Board footer') !!}</h2>
      <div></div>
      <div><p id="id-fpoweredby">{!! __('Powered by') !!}</p></div>
    </footer>
<!-- debuginfo -->
  </div>
</body>
</html>
