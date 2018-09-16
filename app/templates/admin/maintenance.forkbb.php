@extends ('layouts/admin')
      <section class="f-admin f-maintenance-form">
        <h2>{!! __('Maintenance head') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formMaintenance)
    @include ('layouts/form')
@endif
        </div>
      </section>
      <section class="f-admin f-rebuildindex-form">
        <h2>{!! __('Rebuild index head') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formRebuild)
    @include ('layouts/form')
@endif
        </div>
      </section>
