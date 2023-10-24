@include ('layouts/crumbs')
@extends ('layouts/main')
    <!-- PRE start -->
    <!-- PRE h1Before -->
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __('Search') !!}</h1>
    </div>
    <!-- PRE h1After -->
    <!-- PRE crumbsBefore -->
    <div class="f-nav-links">
@yield ('crumbs')
    </div>
    <!-- PRE crumbsAfter -->
@if ($form = $p->form)
    <!-- PRE mainBefore -->
    <div id="fork-search" class="f-main">
      <!-- PRE mainStart -->
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
      <!-- PRE mainEnd -->
    </div>
    <!-- PRE mainAfter -->
@endif
    <!-- PRE end -->
