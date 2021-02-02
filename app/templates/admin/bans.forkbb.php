@extends ('layouts/admin')
@if ($form = $p->formSearch)
      <section id="fork-bnsrch" class="f-admin">
        <h2>{!! __('Ban search head') !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
@if ($form = $p->formBan)
      <section id="fork-bn" class="f-admin">
        <h2>{!! $p->formBanHead !!}</h2>
        <div class="f-fdiv">
    @include ('layouts/form')
        </div>
      </section>
@endif
