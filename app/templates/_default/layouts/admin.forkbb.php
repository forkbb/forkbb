@include ('layouts/crumbs')
@extends ('layouts/main')
    <!-- PRE start -->
    <!-- PRE h1Before -->
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __($p->adminHeader) !!}</h1>
    </div>
    <!-- PRE h1After -->
    <!-- PRE linksBefore -->
    <div class="f-nav-links f-nav-admin{{ $p->mainSuffix or '' }}-links">
@yield ('crumbs')
    </div>
    <!-- PRE linksAfter -->
    <!-- PRE mainBefore -->
    <div class="f-main f-main-admin{{ $p->mainSuffix or '' }}">
      <!-- PRE menuBefore -->
      <div id="fork-a-menu">
@if ($p->aNavigation)
        <nav id="fork-a-nav" class="f-menu">
          <input id="id-an-checkbox" class="f-menu-checkbox" type="checkbox">
          <label id="id-an-label" class="f-menu-toggle" for="id-an-checkbox"><span class="f-menu-tsp">{!! __('Admin menu') !!}</span></label>
          <ul class="f-menu-items">
    @foreach ($p->aNavigation as $key => $val)
            <li id="id-anav-{{ $key }}" class="f-menu-item"><a class="f-menu-a @if ($key == $p->aIndex) active @endif" href="{{ $val[0] }}"><span class="f-menu-span">{!! __($val[1]) !!}</span></a></li>
    @endforeach
          </ul>
        </nav>
@endif
      </div>
      <!-- PRE menuAfter -->
      <!-- PRE contentBefore -->
      <div id="forka">
@yield ('content')
      </div>
      <!-- PRE contentAfter -->
    </div>
    <!-- PRE mainAfter -->
    <!-- PRE end -->
