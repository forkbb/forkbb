<!DOCTYPE html>
<html lang="{{ __('lang_identifier') }}" dir="{{ __('lang_direction') }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{! $p->pageTitle !}}</title>
@foreach ($p->pageHeaders as $pageHeader)
    @if ('style' === $pageHeader['type'])
  <style>{!! $pageHeader['values'][0] !!}</style>
    @elseif ('script' !== $pageHeader['type'])
  <{{ $pageHeader['type'] }} @foreach ($pageHeader['values'] as $key => $val) {{ $key }}="{{ $val }}" @endforeach>
    @endif
@endforeach
</head>
<body>
  <div id="fork" class="@if ($p->fNavigation)f-with-nav @endif @if($p->fPMFlash) f-pm-flash @endif">
    <header id="fork-header">
      <p id="id-fhth1"><a id="id-fhtha" rel="home" href="{{ $p->fRootLink }}">{{ $p->fTitle }}</a></p>
@if ('' != $p->fDescription)
      <p id="id-fhtdesc">{!! $p->fDescription !!}</p>
@endif
    </header>
    <main id="fork-main">
@if ($p->fAnnounce)
    <aside id="fork-announce">
      <p class="f-sim-header">{!! __('Announcement') !!}</p>
      <p id="id-facontent">{!! $p->fAnnounce !!}</p>
    </aside>
@endif
@if ($iswev = $p->fIswev)
    @include ('layouts/iswev')
@endif
@yield ('content')
    </main>
@if ($p->fNavigation)
    <nav id="fork-nav" class="f-menu @if ($p->fNavigation['search']) f-main-nav-search @endif">
      <div id="fork-navdir">
        <input id="id-mn-checkbox" class="f-menu-checkbox" type="checkbox">
        <label id="id-mn-label" class="f-menu-toggle" for="id-mn-checkbox"><span class="f-menu-tsp">{!! __('Main menu') !!}</span></label>
        <ul class="f-menu-items" itemscope itemtype="https://schema.org/SiteNavigationElement" role="menu">
    @foreach ($p->fNavigation as $key => $val)
          <li id="fork-nav-{{ $key }}" class="f-menu-item" itemprop="about" itemscope itemtype="https://schema.org/ItemList" role="menuitem"><!-- inline -->
            <a class="f-menu-a @if ($key == $p->fIndex) active @endif" href="{{ $val[0] }}" @if ($val[2]) title="{{ __($val[2]) }}" @endif itemprop="url">
              <span class="f-menu-span" itemprop="name">{!! __($val[1]) !!}</span>
            </a>
        @if ($val[3])
            <ul class="f-submenu-items" itemscope itemtype="https://schema.org/SiteNavigationElement" role="menu">
            @foreach ($val[3] as $key => $val)
              <li id="fork-nav-{{ $key }}" class="f-menu-item" itemprop="about" itemscope itemtype="https://schema.org/ItemList" role="menuitem">
                @if ($val[0])
                <a class="f-menu-a @if ($key == $p->fSubIndex) active @endif" href="{{ $val[0] }}" @if ($val[2]) title="{{ __($val[2]) }}" @endif itemprop="url">
                  <span class="f-menu-span" itemprop="name">{!! __($val[1]) !!}</span>
                </a>
                @else
                <span class="f-menu-span" itemprop="name">{!! __($val[1]) !!}</span>
                @endif
              </li>
            @endforeach
            </ul>
        @endif
          </li><!-- endinline -->
    @endforeach
        </ul>
    @if ($p->fNavigationUser)
        <ul class="f-menu-user-items" itemscope itemtype="https://schema.org/SiteNavigationElement" role="menu">
        @foreach ($p->fNavigationUser as $key => $val)
          <li id="fork-nav-{{ $key }}" class="f-menu-item @if ($val[4]) f-mi-{{ \implode(' f-mi-', $val[4]) }} @endif" itemprop="about" itemscope itemtype="https://schema.org/ItemList" role="menuitem"><!-- inline -->
            <a class="f-menu-a @if ($key == $p->fIndex) active @endif" href="{{ $val[0] }}" @if ($val[2]) title="{{ __($val[2]) }}" @endif itemprop="url">
              <span class="f-menu-span" itemprop="name">{!! __($val[1]) !!}</span>
            </a>
            @if ($val[3])
            <ul class="f-submenu-items" itemscope itemtype="https://schema.org/SiteNavigationElement" role="menu">
                @foreach ($val[3] as $key => $val)
              <li id="fork-nav-{{ $key }}" class="f-menu-item" itemprop="about" itemscope itemtype="https://schema.org/ItemList" role="menuitem">
                    @if ($val[0])
                <a class="f-menu-a @if ($key == $p->fSubIndex) active @endif" href="{{ $val[0] }}" @if ($val[2]) title="{{ __($val[2]) }}" @endif itemprop="url">
                  <span class="f-menu-span" itemprop="name">{!! __($val[1]) !!}</span>
                </a>
                    @else
                <span class="f-menu-span" itemprop="name">{!! __($val[1]) !!}</span>
                    @endif
              </li>
                @endforeach
            </ul>
            @endif
          </li><!-- endinline -->
        @endforeach
        </ul>
    @endif
      </div>
    </nav>
@endif
    <footer id="fork-footer">
      <p class="f-sim-header">{!! __('Board footer') !!}</p>
      <div id="fork-footer-in">
        <div></div>
        <div><p id="id-fpoweredby">{!! __('Powered by') !!}</p></div>
      </div>
<!-- debuginfo -->
    </footer>
  </div>
@foreach ($p->pageHeaders as $pageHeader)
    @if ('script' === $pageHeader['type'])
        @empty ($pageHeader['values']['inline'])
  <script @foreach ($pageHeader['values'] as $key => $val) {{ $key }}="{{ $val }}" @endforeach></script>
        @else
  <script>{{ $pageHeader['values']['inline'] }}</script>
        @endempty
    @endif
@endforeach
</body>
</html>
