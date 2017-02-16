@extends('layouts/main')
    <section class="f-main f-login">
      <div class="f-wdiv">
        <h2>{!! __('Login') !!}</h2>
        <form class="f-form" method="post" action="{!! $formAction !!}">
          <input type="hidden" name="token" value="{!! $formToken !!}">
          <input type="hidden" name="redirect" value="{{ $formRedirect }}">
          <label class="f-child1" for="id-name">{!! __('Username') !!}</label>
          <input required id="id-name" type="text" name="name" value="{{ $name }}" maxlength="25" autofocus="autofocus" spellcheck="false" tabindex="1">
          <label class="f-child1" for="id-password">{!! __('Password') !!}<a class="f-forgetlink" href="{!! $forgetLink !!}" tabindex="5">{!! __('Forgotten pass') !!}</a></label>
          <input required id="id-password" type="password" name="password" tabindex="2">
@if($formSave)
          <label class="f-child2"><input type="checkbox" name="save" value="1" tabindex="3" checked="checked">{!! __('Remember me') !!}</label>
@else
          <label class="f-child2"><input type="checkbox" name="save" value="1" tabindex="3">{!! __('Remember me') !!}</label>
@endif
          <input class="f-btn" type="submit" name="login" value="{!! __('Login') !!}" tabindex="4">
        </form>
      </div>
@if($regLink)
      <div class="f-wdiv">
        <p class="f-child3"><a href="{!! $regLink !!}" tabindex="6">{!! __('Not registered') !!}</a></p>
      </div>
@endif
    </section>
