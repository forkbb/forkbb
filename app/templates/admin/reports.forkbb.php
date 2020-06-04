@extends ('layouts/admin')
@if ($form = $p->formNew)
      <section class="f-admin f-reports f-reports-new">
        <h2>{!! __('New reports ') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
@if ($form = $p->formOld)
      <section class="f-admin f-reports f-reports-old">
        <h2>{!! __('10 last read reports') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
