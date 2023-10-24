@include ('layouts/crumbs')
@extends ('layouts/main')
    <!-- PRE start -->
    <!-- PRE h1Before -->
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __($p->legend) !!}</h1>
    </div>
    <!-- PRE h1After -->
    <!-- PRE crumbsBefore -->
    <div class="f-nav-links">
@yield ('crumbs')
    </div>
    <!-- PRE crumbsAfter -->
@if ($form = $p->form)
    <!-- PRE mainBefore -->
    <section id="fork-sendemail" class="f-post-form">
      <!-- PRE mainStart -->
      <h2>{!! __('Send email title') !!}</h2>
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
      <!-- PRE mainEnd -->
    </section>
    <!-- PRE mainAfter -->
@endif
    <!-- PRE end -->
