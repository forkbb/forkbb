@extends ('layouts/admin')
      <section class="f-admin @if ($p->classForm) f-{{ \implode('-form f-', $p->classForm) }}-form @endif">
        <h2>{!! __($p->titleForm) !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->form)
    @include ('layouts/form')
@endif
        </div>
      </section>
