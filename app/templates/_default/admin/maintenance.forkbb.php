@extends ('layouts/admin')
      <section id="fork-mnt" class="f-admin">
        <h2>{!! __('Maintenance head') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formMaintenance)
    @include ('layouts/form')
@endif
        </div>
      </section>
      <section id="fork-rbld" class="f-admin">
        <h2>{!! __('Rebuild index head') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formRebuild)
    @include ('layouts/form')
@endif
        </div>
      </section>
      <section id="fork-clcch" class="f-admin">
        <h2>{!! __('Clear cache head') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formClearCache)
    @include ('layouts/form')
@endif
        </div>
      </section>
