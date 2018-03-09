@extends ('layouts/main')
    <div class="f-main f-main-admin">
      <aside class="f-admin-menu">
@if ($p->aNavigation)
        <nav class="f-admin-nav f-menu">
          <input id="id-an-checkbox" class="f-menu-checkbox" style="display: none;" type="checkbox">
          <label class="f-menu-toggle" for="id-an-checkbox"></label>
  @foreach ($p->aNavigation as $aNameSub => $aNavigationSub)
          <h2 class="f-menu-items">{!! __($aNameSub) !!}</h2>
          <ul class="f-menu-items">
    @foreach ($aNavigationSub as $key => $val)
            <li id="id-anav-{{ $key }}" class="f-menu-item"><a class="f-menu-a @if ($key == $p->aIndex) active @endif" href="{!! $val[0] !!}">{!! $val[1] !!}</a></li>
    @endforeach
          </ul>
  @endforeach
        </nav>
@endif
      </aside>
      <div class="f-admin-wrap">
@yield ('content')
      </div>
    </div>
