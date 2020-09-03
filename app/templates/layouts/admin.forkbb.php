@include ('layouts/crumbs')
@extends ('layouts/main')
    <div class="f-nav-links f-nav-admin{!! $p->mainSuffix or '' !!}-links">
@yield ('crumbs')
    </div>
    <div class="f-main f-main-admin{!! $p->mainSuffix or '' !!}">
      <aside class="f-admin-menu">
@if ($p->aNavigation)
        <nav class="f-admin-nav f-menu">
          <input id="id-an-checkbox" class="f-menu-checkbox" type="checkbox">
          <label id="id-an-label" class="f-menu-toggle" for="id-an-checkbox"></label>
          <ul class="f-menu-items">
    @foreach ($p->aNavigation as $key => $val)
            <li id="id-anav-{{ $key }}" class="f-menu-item"><a class="f-menu-a @if ($key == $p->aIndex) active @endif" href="{!! $val[0] !!}">{!! $val[1] !!}</a></li>
    @endforeach
          </ul>
        </nav>
@endif
      </aside>
      <div class="f-admin-wrap">
@yield ('content')
      </div>
    </div>
