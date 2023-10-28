@extends ('layouts/main')
    <!-- PRE start -->
@if ($form = $p->form)
    <!-- PRE mainBefore -->
    <section id="fork-login" class="f-main">
      <!-- PRE mainStart -->
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Login') !!}</h2>
    @include ('layouts/form')
      </div>
      <!-- PRE mainEnd -->
    </section>
    <!-- PRE mainAfter -->
@endif
@if ($form = $p->formOAuth)
    <!-- PRE oauthBefore -->
    <div id="fork-oauth" class="f-main">
      <!-- PRE oauthStart -->
      <div class="f-fdiv f-lrdiv">
    @include ('layouts/form')
      </div>
      <!-- PRE oauthEnd -->
    </div>
    <!-- PRE oauthAfter -->
@endif
@if ($p->regLink)
    <!-- PRE lgrgBefore -->
    <div id="fork-lgrglnk" class="f-main">
      <!-- PRE lgrgStart -->
      <div class="f-fdiv f-lrdiv">
        <div class="f-btns">
          <a class="f-btn f-fbtn" href="{{ $p->regLink }}">{!! __('Not registered') !!}</a>
        </div>
      </div>
      <!-- PRE lgrgEnd -->
    </div>
    <!-- PRE lgrgAfter -->
@endif
    <!-- PRE end -->
