@extends ('layouts/main')
    <section class="f-main f-register">
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Register') !!}</h2>
@if ($form = $p->form)
    @include ('layouts/form')
@endif
      </div>
    </section>
