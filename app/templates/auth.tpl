@extends('layouts/main')
    <section class="f-main f-login">
      <div class="f-wrapdiv">
        <h2>{!! __('Login') !!}</h2>
        <form id="id-aform" method="post" action="{!! $formAction !!}">
          <input type="hidden" name="token" value="{!! $formToken !!}">
          <input type="hidden" name="redirect" value="{{ $formRedirect }}">
          <label id="id-label1" for="id-fname">{!! __('Username') !!}</label>
          <input required id="id-fname" type="text" name="name" value="{{ $name }}" maxlength="25" autofocus="autofocus" spellcheck="false" tabindex="1">
          <label id="id-label2" for="id-fpassword">{!! __('Password') !!}<a class="f-forgetlink" href="{!! $forgetLink !!}" tabindex="5">{!! __('Forgotten pass') !!}</a></label>
          <input required id="id-fpassword" type="password" name="password" tabindex="2">
@if($formSave)
          <label id="id-label3"><input type="checkbox" name="save" value="1" tabindex="3" checked="checked">{!! __('Remember me') !!}</label>
@else
          <label id="id-label3"><input type="checkbox" name="save" value="1" tabindex="3">{!! __('Remember me') !!}</label>
@endif
          <input class="f-btn" type="submit" name="login" value="{!! __('Login') !!}" tabindex="4">
        </form>
      </div>
@if($regLink)
      <div class="f-wrapdiv">
        <p><a href="{!! $regLink !!}" tabindex="6">{!! __('Not registered') !!}</a></p>
      </div>
@endif
    </section>
