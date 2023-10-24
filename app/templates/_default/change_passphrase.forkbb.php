@extends ('layouts/main')
    <!-- PRE start -->
@if ($form = $p->form)
    <!-- PRE mainBefore -->
    <section id="fork-changepass" class="f-main">
      <!-- PRE mainStart -->
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Change pass') !!}</h2>
    @include ('layouts/form')
      </div>
      <!-- PRE mainEnd -->
    </section>
    <!-- PRE mainAfter -->
@endif
    <!-- PRE end -->
