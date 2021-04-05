@extends ('layouts/pm')
@if ($form = $p->form)
    <section class="f-pm f-pm-form f-{{ $p->formClass }}-form">
      <h2>{!! __($p->formTitle) !!}</h2>
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
    </section>
@endif
