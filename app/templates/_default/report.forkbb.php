@include ('layouts/crumbs')
@extends ('layouts/main')
    <!-- PRE start -->
    <!-- PRE h1Before -->
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __('Report post') !!}</h1>
    </div>
    <!-- PRE h1After -->
    <!-- PRE linksBefore -->
    <div class="f-nav-links">
@yield ('crumbs')
    </div>
    <!-- PRE linksAfter -->
@if ($form = $p->form)
    <!-- PRE mainBefore -->
    <div id="fork-report" class="f-post-form">
      <!-- PRE mainStart -->
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
      <!-- PRE mainEnd -->
    </div>
    <!-- PRE mainAfter -->
@endif
    <!-- PRE end -->
