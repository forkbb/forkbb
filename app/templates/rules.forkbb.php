@include ('layouts/crumbs')
@extends ('layouts/main')
    <div class="f-nav-links">
@yield ('crumbs')
    </div>
    <section id="fork-rules" class="f-main">
      <h2>{!! __('Forum rules') !!}</h2>
      <div id="id-rules">{!! $p->rules !!}</div>
@if ($form = $p->form)
      <div class="f-fdiv f-lrdiv">
    @include ('layouts/form')
      </div>
@endif
    </section>
