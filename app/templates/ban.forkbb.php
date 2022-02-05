@extends ('layouts/main')
    <section id="fork-ban" class="f-main">
      <h2>{!! __('Info') !!}</h2>
@if ($p->bannedIp)
      <p>{!! __('Your IP is blocked') !!}</p>
@else
      <p>{!! __('You are banned') !!}</p>
@endif
@if ($p->ban['expire'])
      <p>{!! __(['The ban expires %s', dt($p->ban['expire'], true)]) !!}</p>
@endif
@if ($p->ban['message'])
      <p>{!! __('Ban message for you') !!}</p>
      <p><b>{{ $p->ban['message'] }}</b></p>
@endif
      <p>{!! __(['Ban message contact %s', $p->adminEmail]) !!}</p>
    </section>
