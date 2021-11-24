@extends ('layouts/main')
@if ($form = $p->form)
    <section id="fork-login" class="f-main">
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Login') !!}</h2>
    @include ('layouts/form')
      </div>
    @if ($p->regLink)
      <div id="fork-lgrglnk" class="f-fdiv f-lrdiv">
        <div class="f-btns">
          <a class="f-btn f-fbtn" href="{{ $p->regLink }}">{!! __('Not registered') !!}</a>
        </div>
      </div>
    @endif
    </section>
@endif
