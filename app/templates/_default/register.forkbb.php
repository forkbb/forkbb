@extends ('layouts/main')
    <!-- PRE start -->
@if ($form = $p->form)
    <!-- PRE mainBefore -->
    <section id="fork-reg" class="f-main">
      <!-- PRE mainStart -->
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Register') !!}</h2>
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
    <!-- PRE end -->
