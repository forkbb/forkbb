@extends ('layouts/main')
    <section class="f-main f-message">
      <h2>{{ __('Info') }}</h2>
      <p>{!! __('Ban message') !!}</p>
@if ($p->ban['expire'])
      <p>{!! __('Ban message 2', dt($p->ban['expire'], true)) !!}</p>
@endif
@if ($p->ban['message'])
      <p>{!! __('Ban message 3') !!}</p>
      <p><b>{{ $p->ban['message'] }}</b></p>
@endif
      <p>{!! __('Ban message 4', $p->adminEmail, $p->adminEmail) !!}</p>
    </section>
