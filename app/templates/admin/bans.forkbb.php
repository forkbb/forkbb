@extends ('layouts/admin')
@if ($form = $p->formSearch)
      <section class="f-admin f-search-bans">
        <h2>{!! __('Ban search head') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
@if ($form = $p->formBan)
      <section class="f-admin f-bans">
        <h2>{!! $p->formBanHead !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
