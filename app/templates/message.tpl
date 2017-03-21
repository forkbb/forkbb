@extends('layouts/main')
    <section class="f-main f-message">
      <h2>{!! __('Info') !!}</h2>
      <p>{!! $message !!}</p>
@if($back)
      <p><a href="javascript: history.go(-1)">{!! __('Go back') !!}</a></p>
@endif
    </section>
