@extends ('layouts/main')
@if ($form = $p->form)
    <section id="fork-reg" class="f-main">
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Register') !!}</h2>
    @include ('layouts/form')
      </div>
    </section>
@endif
@if ($form = $p->formOAuth)
    <div id="fork-oauth" class="f-main">
      <div class="f-fdiv f-lrdiv">
    @include ('layouts/form')
      </div>
    </div>
@endif
