@extends ('layouts/pm')
@if ($form = $p->form)
    <section id="{{ $p->formId }}" class="f-pmform">
      <h2>{!! __($p->formTitle) !!}</h2>
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
    </section>
@endif
