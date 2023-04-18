@include ('layouts/crumbs')
@extends ('layouts/main')
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __($p->legend) !!}</h1>
    </div>
    <div class="f-nav-links">
@yield ('crumbs')
    </div>
@if ($form = $p->form)
    <section id="fork-sendemail" class="f-post-form">
      <h2>{!! __('Send email title') !!}</h2>
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
    </section>
@endif
