@include ('layouts/crumbs')
@extends ('layouts/main')
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __('Forum rules') !!}</h1>
    </div>
    <div class="f-nav-links">
@yield ('crumbs')
    </div>
    <div id="fork-rules" class="f-main">
      <div id="id-rules">{!! $p->rules !!}</div>
@if ($form = $p->form)
      <div id="fork-rconf" class="f-fdiv f-lrdiv">
    @include ('layouts/form')
      </div>
@endif
    </div>
