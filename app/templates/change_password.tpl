@extends('layouts/main')
    <section class="f-main f-login">
      <div class="f-wdiv">
        <h2>{!! __('Change pass') !!}</h2>
        <form class="f-form" method="post" action="{!! $formAction !!}">
          <input type="hidden" name="token" value="{!! $formToken !!}">
          <label class="f-child1" for="id-password">{!! __('New pass') !!}</label>
          <input required id="id-password" type="password" name="password" pattern=".{8,}" autofocus="autofocus" tabindex="1">
          <label class="f-child1" for="id-password2">{!! __('Confirm new pass') !!}</label>
          <input required id="id-password2" type="password" name="password2" pattern=".{8,}" tabindex="2">
          <label class="f-child2">{!! __('Pass info') !!}</label>
          <input class="f-btn" type="submit" name="login" value="{!! __('Save') !!}" tabindex="3">
        </form>
      </div>
    </section>
