@extends('layouts/main')
    <section class="f-main f-login">
      <div class="f-lrdiv">
        <h2>{!! __('Change pass') !!}</h2>
        <form class="f-form" method="post" action="{!! $formAction !!}">
          <input type="hidden" name="token" value="{!! $formToken !!}">
          <div>
            <label class="f-child1 f-req" for="id-password">{!! __('New pass') !!}</label>
            <input required class="f-ctrl" id="id-password" type="password" name="password" pattern="^.{16,}$" autofocus="autofocus" tabindex="1">
          </div>
          <div>
            <label class="f-child1 f-req" for="id-password2">{!! __('Confirm new pass') !!}</label>
            <input required class="f-ctrl" id="id-password2" type="password" name="password2" pattern="^.{16,}$" tabindex="2">
            <label class="f-child4">{!! __('Pass format') !!} {!! __('Pass info') !!}</label>
          </div>
          <div>
            <input class="f-btn" type="submit" name="login" value="{!! __('Change passphrase') !!}" tabindex="3">
          </div>
        </form>
      </div>
    </section>
