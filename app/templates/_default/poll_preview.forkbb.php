@include ('layouts/crumbs')
@extends ('layouts/main')
    <!-- PRE start -->
    <!-- PRE h1Before -->
    <div class="f-mheader">
      <h1 id="fork-h1">{{ $p->model->name }}</h1>
    </div>
    <!-- PRE h1After -->
    <!-- PRE linksBBefore -->
    <div class="f-nav-links">
@yield ('crumbs')
    </div>
    <!-- PRE linksBAfter -->
    <!-- PRE mainBefore -->
    <section id="fork-topic" class="f-main">
      <h2>{!! __('Post list') !!}</h2>
      <article class="f-post f-post-first">
        <address class="f-post-user">
        </address>
        <div class="f-post-body">
          <div class="f-post-main">
@if ($poll = $p->model->poll)
            @include ('layouts/poll')
@endif
          </div>
        </div>
      </article>
    </section>
    <!-- PRE mainAfter -->
    <!-- PRE linksABefore -->
    <div class="f-nav-links">
@yield ('crumbs')
    </div>
    <!-- PRE linksAAfter -->
    <!-- PRE end -->
