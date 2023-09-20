@extends ('layouts/main')
@if ($form = $p->form)
    <section id="fork-resetpass" class="f-main">
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Passphrase reset') !!}</h2>
    @include ('layouts/form')
      </div>
    </section>
@endif
