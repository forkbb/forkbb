@extends ('layouts/main')
    <!-- PRE start -->
@if ($form = $p->form)
    <!-- PRE mainBefore -->
    <section id="fork-resetpass" class="f-main">
      <!-- PRE mainStart -->
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Passphrase reset') !!}</h2>
    @include ('layouts/form')
      </div>
      <!-- PRE mainEnd -->
    </section>
    <!-- PRE mainAfter -->
@endif
    <!-- PRE end -->
