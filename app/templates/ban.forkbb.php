@extends ('layouts/main')
    <section id="fork-ban" class="f-main">
      <h2>{!! __('Info') !!}</h2>
@if ($p->bannedIp)
      <p>{!! __('Ban message 1') !!}</p>
@else
      <p>{!! __('Ban message') !!}</p>
@endif
@if ($p->ban['expire'])
      <p>{!! __(['Ban message 2', dt($p->ban['expire'], true)]) !!}</p>
@endif
@if ($p->ban['message'])
      <p>{!! __('Ban message 3') !!}</p>
      <p><b>{{ $p->ban['message'] }}</b></p>
@endif
      <p>{!! __(['Ban message 4', $p->adminEmail]) !!}</p>
    </section>
