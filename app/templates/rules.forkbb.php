@include ('layouts/crumbs')
@extends ('layouts/main')
    <div class="f-nav-links">
@yield ('crumbs')
    </div>
    <section class="f-main f-rules">
      <h2>{!! __('Forum rules') !!}</h2>
      <div id="id-rules">{!! $p->rules !!}</div>
@if ($form = $p->form)
      <div class="f-fdiv f-lrdiv">
    @include ('layouts/form')
      </div>
@endif
    </section>
