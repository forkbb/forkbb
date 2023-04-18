@include ('layouts/crumbs')
@extends ('layouts/main')
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __('Report post') !!}</h1>
    </div>
    <div class="f-nav-links">
@yield ('crumbs')
    </div>
@if ($form = $p->form)
    <div id="fork-report" class="f-post-form">
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
    </div>
@endif
