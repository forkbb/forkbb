@extends ('layouts/admin')
@if ($form = $p->formSearch)
      <section id="fork-ausersrch" class="f-admin">
        <h2>{!! __('User search head') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
@if ($form = $p->formIP)
      <section id="fork-ipsrch" class="f-admin">
        <h2>{!! __('IP search head') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
@if ($form = $p->formNew)
      <section id="fork-newuser" class="f-admin">
        <h2>{!! __('New user head') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
@if ($form = $p->formRecalculate)
      <section id="fork-recalc" class="f-admin">
        <h2>{!! __('Recalculate head') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
