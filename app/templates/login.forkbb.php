@extends ('layouts/main')
    <section class="f-main f-login">
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Login') !!}</h2>
@if ($form = $p->form)
    @include ('layouts/form')
@endif
      </div>
@if ($p->regLink)
      <div id="fork-lgrglnk" class="f-fdiv f-lrdiv">
        <div class="f-btns">
          <a class="f-btn f-fbtn" href="{{ $p->regLink }}">{!! __('Not registered') !!}</a>
        </div>
      </div>
@endif
    </section>
