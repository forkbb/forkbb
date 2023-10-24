@include ('layouts/crumbs')
@extends ('layouts/main')
    <!-- PRE start -->
    <!-- PRE h1Before -->
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __($p->pmHeader) !!}</h1>
    </div>
    <!-- PRE h1After -->
    <!-- PRE crumbsBefore -->
    <div class="f-nav-links f-nav-pm-links">
@yield ('crumbs')
    </div>
    <!-- PRE crumbsAfter -->
    <!-- PRE mainBefore -->
    <div class="f-main f-main-pm">
      <!-- PRE menuBefore -->
      <div id="fork-pm-menu">
@if ($p->pmNavigation)
        <nav id="fork-pm-nav" class="f-menu">
          <input id="id-pmn-checkbox" class="f-menu-checkbox" type="checkbox">
          <label id="id-pmn-label" class="f-menu-toggle" for="id-pmn-checkbox"><span class="f-menu-tsp">{!! __('PM menu') !!}</span></label>
          <ul class="f-menu-items">
    @foreach ($p->pmNavigation as $key => $val)
            <li id="id-pmnav-{{ $key }}" class="f-menu-item">
        @if (null === $val[0])
              <span class="f-menu-a f-menu-space">
                <span class="f-menu-span">&nbsp;</span>
              </span>
        @elseif (true === $val[0])
              <h3 class="f-menu-a f-menu-h3">
                <span class="f-menu-span">{!! __($val[1]) !!}</span>
              </h3>
        @elseif (false === $val[0])
              <span class="f-menu-a f-menu-text">
                <span class="f-menu-span">{!! __($val[1]) !!}</span>
              </span>
        @else
              <a class="f-menu-a @if ($key == $p->pmIndex) active @endif" href="{{ $val[0] }}">
                <span class="f-menu-span">{!! __($val[1]) !!}</span>
              </a>
        @endif
            </li>
    @endforeach
          </ul>
        </nav>
@endif
      </div>
      <!-- PRE menuAfter -->
      <!-- PRE contentBefore -->
      <div id="forkpm">
@yield ('content')
      </div>
      <!-- PRE contentAfter -->
    </div>
    <!-- PRE mainAfter -->
    <!-- PRE end -->
