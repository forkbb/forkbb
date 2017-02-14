@extends('layouts/main')
    <section class="f-main f-message">
      <h2>{{ __('Info') }}</h2>
      <p>{!! __('Ban message') !!}</p>
@if(! empty($banned['expire']))
      <p>{!! __('Ban message 2') !!} {{ $banned['expire'] }}</p>
@endif
@if(! empty($banned['message']))
      <p>{!! __('Ban message 3') !!}</p>
      <p><strong>{{ $banned['message'] }}</strong></p>
@endif
      <p>{!! __('Ban message 4'] !!) <a href="mailto:{{ $adminEmail }}">{{ $adminEmail }}</a>.</p>
    </section>
