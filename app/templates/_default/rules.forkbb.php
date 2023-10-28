@include ('layouts/crumbs')
@extends ('layouts/main')
    <!-- PRE start -->
    <!-- PRE h1Before -->
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __('Forum rules') !!}</h1>
    </div>
    <!-- PRE h1After -->
    <!-- PRE linksBefore -->
    <div class="f-nav-links">
@yield ('crumbs')
    </div>
    <!-- PRE linksAfter -->
    <!-- PRE mainBefore -->
    <div id="fork-rules" class="f-main">
      <div id="id-rules">{!! $p->rules !!}</div>
    </div>
    <!-- PRE mainAfter -->
@if ($form = $p->form)
    <!-- PRE regBefore -->
    <div id="fork-rconf" class="f-main">
      <!-- PRE regStart -->
      <div class="f-fdiv f-lrdiv">
    @include ('layouts/form')
      </div>
      <!-- PRE regEnd -->
    </div>
    <!-- PRE regAfter -->
@endif
    <!-- PRE end -->
