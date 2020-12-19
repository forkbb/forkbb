@extends ('layouts/main')
    <section class="f-main f-login">
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Login') !!}</h2>
@if ($form = $p->form)
    @include ('layouts/form')
@endif
      </div>
@if ($p->regLink)
      <div class="f-fdiv f-lrdiv">
        <p class="f-child3"><a href="{{ $p->regLink }}" tabindex="6">{!! __('Not registered') !!}</a></p>
      </div>
@endif
    </section>
