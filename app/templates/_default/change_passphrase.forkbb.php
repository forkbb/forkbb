@extends ('layouts/main')
@if ($form = $p->form)
    <section id="fork-changepass" class="f-main">
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Change pass') !!}</h2>
    @include ('layouts/form')
      </div>
    </section>
@endif
