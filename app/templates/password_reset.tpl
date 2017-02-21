@extends('layouts/main')
    <section class="f-main f-login">
      <div class="f-wdiv">
        <h2>{!! __('Password reset') !!}</h2>
        <form class="f-form" method="post" action="{!! $formAction !!}">
          <input type="hidden" name="token" value="{!! $formToken !!}">
          <label class="f-child1" for="id-email">{!! __('Email') !!}</label>
          <input required id="id-email" type="text" name="email" value="{{ $email }}" maxlength="80" autofocus="autofocus" spellcheck="false" tabindex="1">
          <label class="f-child2">{!! __('Password reset info') !!}</label>
          <input class="f-btn" type="submit" name="submit" value="{!! __('Submit') !!}" tabindex="2">
        </form>
      </div>
    </section>
