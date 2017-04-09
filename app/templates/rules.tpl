@extends('layouts/main')
    <section class="f-main f-rules">
      <h2>{!! $title !!}</h2>
      <div id="id-rules">{!! $rules !!}</div>
@if($formAction)
      <div class="f-fdiv f-lrdiv">
        <form class="f-form" method="post" action="{!! $formAction !!}">
          <input type="hidden" name="token" value="{!! $formToken !!}">
          <div>
            <label class="f-child2"><input type="checkbox" name="agree" value="{!! $formHash !!}" tabindex="1">{!! __('Agree') !!}</label>
          </div>
          <div>
            <input class="f-btn" type="submit" name="register" value="{!! __('Register') !!}" tabindex="2">
          </div>
        </form>
      </div>
@endif
    </section>
