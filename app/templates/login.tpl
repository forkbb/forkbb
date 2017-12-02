@extends ('layouts/main')
    <section class="f-main f-login">
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Login') !!}</h2>
        <form class="f-form" method="post" action="{!! $p->formAction !!}">
          <input type="hidden" name="token" value="{!! $p->formToken !!}">
          <input type="hidden" name="redirect" value="{{ $p->redirect }}">
          <fieldset>
            <dl>
              <dt><label class="f-child1 f-req" for="id-username">{!! __('Username') !!}</label></dt>
              <dd>
                <input required class="f-ctrl" id="id-username" type="text" name="username" value="{{ $p->username }}" maxlength="25" autofocus spellcheck="false" tabindex="1">
              </dd>
            </dl>
            <dl>
              <dt><label class="f-child1 f-req" for="id-password">{!! __('Passphrase') !!}<a class="f-forgetlink" href="{!! $p->forgetLink !!}" tabindex="5">{!! __('Forgotten pass') !!}</a></label></dt>
              <dd>
                <input required class="f-ctrl" id="id-password" type="password" name="password" tabindex="2">
              </dd>
            </dl>
            <dl>
              <dt></dt>
@if ($p->save)
              <dd><label class="f-child2"><input type="checkbox" name="save" value="1" tabindex="3" checked>{!! __('Remember me') !!}</label></dd>
@else
              <dd><label class="f-child2"><input type="checkbox" name="save" value="1" tabindex="3">{!! __('Remember me') !!}</label></dd>
@endif
            </dl>
          </fieldset>
          <p>
            <input class="f-btn" type="submit" name="login" value="{!! __('Sign in') !!}" tabindex="4">
          </p>
        </form>
      </div>
@if ($p->regLink)
      <div class="f-fdiv f-lrdiv">
        <p class="f-child3"><a href="{!! $p->regLink !!}" tabindex="6">{!! __('Not registered') !!}</a></p>
      </div>
@endif
    </section>
