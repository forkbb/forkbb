@extends ('layouts/admin')
@if ($form = $p->formSearch)
      <section class="f-admin f-search-user-form">
        <h2>{!! __('User search head') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
@if ($form = $p->formIP)
      <section class="f-admin f-search-ip-form">
        <h2>{!! __('IP search head') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
