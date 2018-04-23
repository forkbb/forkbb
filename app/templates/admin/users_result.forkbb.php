@extends ('layouts/admin')
      <section class="f-admin f-search-user-form">
        <h2>{!! __('Results head') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formResult)
  @include ('layouts/form')
@endif
        </div>
      </section>
