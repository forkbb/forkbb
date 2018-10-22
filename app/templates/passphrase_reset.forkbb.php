@extends ('layouts/main')
    <section class="f-main f-login">
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Passphrase reset') !!}</h2>
@if ($form = $p->form)
    @include ('layouts/form')
@endif
      </div>
    </section>
