@extends('layouts/main')
    <section class="f-main f-register">
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Register') !!}</h2>
        <form class="f-form" method="post" action="{!! $formAction !!}">
          <input type="hidden" name="token" value="{!! $formToken !!}">
          <input type="hidden" name="agree" value="{!! $agree !!}">
          <input type="hidden" name="on" value="{!! $on !!}">
          <div>
            <label class="f-child1 f-req" for="id-email">{!! __('Email') !!}</label>
            <input required class="f-ctrl" id="id-email" type="text" name="email" value="{{ $email }}" maxlength="80" pattern=".+@.+" autofocus spellcheck="false" tabindex="1">
            <span class="f-child4 f-fhint">{!! __('Email info') !!}</span>
          </div>
          <div>
            <label class="f-child1 f-req" for="id-username">{!! __('Username') !!}</label>
            <input required class="f-ctrl" id="id-username" type="text" name="username" value="{{ $username }}" maxlength="25" pattern="^.{2,25}$" spellcheck="false" tabindex="2">
            <span class="f-child4 f-fhint">{!! __('Login format') !!}</span>
          </div>
          <div>
            <label class="f-child1 f-req" for="id-password">{!! __('Passphrase') !!}</label>
            <input required class="f-ctrl" id="id-password" type="password" name="password" pattern="^.{16,}$" tabindex="3">
            <span class="f-child4 f-fhint">{!! __('Pass format') !!} {!! __('Pass info') !!}</span>
          </div>
          <div>
            <input class="f-btn" type="submit" name="register" value="{!! __('Sign up') !!}" tabindex="4">
          </div>
        </form>
      </div>
    </section>
