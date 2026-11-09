@include ('layouts/crumbs')
@extends ('layouts/main')
    <!-- PRE start -->
    <!-- PRE h1Before -->
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __('Privacy policy') !!}</h1>
    </div>
    <!-- PRE h1After -->
    <!-- PRE linksBefore -->
    <div class="f-nav-links">
@yield ('crumbs')
    </div>
    <!-- PRE linksAfter -->
    <!-- PRE mainBefore -->
    <div id="fork-rules" class="f-main">
      <div id="id-rules">{!! $p->privacy !!}</div>
    </div>
    <!-- PRE mainAfter -->
    <!-- PRE end -->
