@extends('layouts/main')
    <div class="f-main clearfix">
      <aside class="f-admin-menu">
@if(!empty($aNavigation))
        <nav class="admin-nav f-menu">
          <input id="admin-nav-checkbox" style="display: none;" type="checkbox">
          <label class="f-menu-toggle" for="admin-nav-checkbox"></label>
@foreach($aNavigation as $aNameSub => $aNavigationSub)
          <h2 class="f-menu-items">{!! __($aNameSub) !!}</h2>
          <ul class="f-menu-items">
@foreach($aNavigationSub as $key => $val)
@if($key == $aIndex)
            <li><a id="anav-{{ $key }}" class="active" href="{!! $val[0] !!}">{!! $val[1] !!}</a></li>
@else
            <li><a id="anav-{{ $key }}" href="{!! $val[0] !!}">{!! $val[1] !!}</a></li>
@endif
@endforeach
          </ul>
@endforeach
        </nav>
@endif
      </aside>
@yield('content')
    </div>
