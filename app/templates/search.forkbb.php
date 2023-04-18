@include ('layouts/crumbs')
@extends ('layouts/main')
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __('Search') !!}</h1>
    </div>
    <div class="f-nav-links">
@yield ('crumbs')
    </div>
@if ($form = $p->form)
    <div id="fork-search" class="f-main">
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
    </div>
@endif
