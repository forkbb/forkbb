@extends ('layouts/admin')
      <section class="f-admin">
        <h2>{!! __('Group delete') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->form)
  @include ('layouts/form')
@endif
        </div>
      </section>
