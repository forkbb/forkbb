@extends('layouts/main')
    <section class="f-main f-login">
      <div class="f-lrdiv">
        <h2>{!! __('Login') !!}</h2>
        <form class="f-form" method="post" action="{!! $formAction !!}">
          <input type="hidden" name="token" value="{!! $formToken !!}">
          <input type="hidden" name="redirect" value="{{ $redirect }}">
          <div>
            <label class="f-child1 f-req" for="id-username">{!! __('Username') !!}</label>
            <input required class="f-ctrl" id="id-username" type="text" name="username" value="{{ $username }}" maxlength="25" autofocus="autofocus" spellcheck="false" tabindex="1">
          </div>
          <div>
            <label class="f-child1 f-req" for="id-password">{!! __('Passphrase') !!}<a class="f-forgetlink" href="{!! $forgetLink !!}" tabindex="5">{!! __('Forgotten pass') !!}</a></label>
            <input required class="f-ctrl" id="id-password" type="password" name="password" tabindex="2">
          </div>
          <div>
@if($save)
          <label class="f-child2"><input type="checkbox" name="save" value="1" tabindex="3" checked="checked">{!! __('Remember me') !!}</label>
@else
          <label class="f-child2"><input type="checkbox" name="save" value="1" tabindex="3">{!! __('Remember me') !!}</label>
@endif
          </div>
          <div>
            <input class="f-btn" type="submit" name="login" value="{!! __('Sign in') !!}" tabindex="4">
          </div>
        </form>
      </div>
@if($regLink)
      <div class="f-lrdiv">
        <p class="f-child3"><a href="{!! $regLink !!}" tabindex="6">{!! __('Not registered') !!}</a></p>
      </div>
@endif
    </section>
