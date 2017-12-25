@extends ('layouts/admin')
      <section class="f-admin @if ($p->classForm) {!! $p->classForm !!} @endif">
        <h2>{!! $p->titleForm !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->form)
  @include ('layouts/form')
@endif
        </div>
      </section>
