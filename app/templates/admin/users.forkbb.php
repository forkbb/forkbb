@extends ('layouts/admin')
      <section class="f-admin f-search-user-form">
        <h2>{!! __('User search head') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formSearch)
  @include ('layouts/form')
@endif
        </div>
      </section>
      <section class="f-admin f-search-ip-form">
        <h2>{!! __('IP search head') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formIP)
  @include ('layouts/form')
@endif
        </div>
      </section>
