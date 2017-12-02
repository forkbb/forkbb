@extends ('layouts/main')
    <section class="f-main f-message">
      <h2>{{ __('Info') }}</h2>
      <p>{!! __('Ban message') !!}</p>
@if (! empty($p->ban['expire']))
      <p>{!! __('Ban message 2', $p->ban['expire']) !!}</p>
@endif
@if (! empty($p->ban['message']))
      <p>{!! __('Ban message 3') !!}</p>
      <p><strong>{{ $p->ban['message'] }}</strong></p>
@endif
      <p>{!! __('Ban message 4', $p->adminEmail, $p->adminEmail) !!}</p>
    </section>
