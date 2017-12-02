@extends ('layouts/main')
    <div class="f-main clearfix">
      <aside class="f-admin-menu">
@if ($p->aNavigation)
        <nav class="admin-nav f-menu">
          <input id="admin-nav-checkbox" style="display: none;" type="checkbox">
          <label class="f-menu-toggle" for="admin-nav-checkbox"></label>
  @foreach ($p->aNavigation as $aNameSub => $aNavigationSub)
          <h2 class="f-menu-items">{!! __($aNameSub) !!}</h2>
          <ul class="f-menu-items">
    @foreach ($aNavigationSub as $key => $val)
            <li><a id="anav-{{ $key }}" @if ($key == $p->aIndex) class="active" @endif href="{!! $val[0] !!}">{!! $val[1] !!}</a></li>
    @endforeach
          </ul>
  @endforeach
        </nav>
@endif
      </aside>
@yield ('content')
    </div>
