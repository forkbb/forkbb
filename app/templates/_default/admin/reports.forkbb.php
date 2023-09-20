@extends ('layouts/admin')
@if ($form = $p->formNew)
      <section id="fork-rprt-new" class="f-admin f-reports">
        <h2>{!! __('New reports ') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
@if ($form = $p->formOld)
      <section id="fork-rprt-old" class="f-admin f-reports">
        <h2>{!! __('10 last read reports') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
