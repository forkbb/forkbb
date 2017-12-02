@extends ('layouts/main')
    <section class="f-main f-register">
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Register') !!}</h2>
        <form class="f-form" method="post" action="{!! $p->formAction !!}">
          <input type="hidden" name="token" value="{!! $p->formToken !!}">
          <input type="hidden" name="agree" value="{!! $p->agree !!}">
          <input type="hidden" name="on" value="{!! $p->on !!}">
          <fieldset>
            <dl>
              <dt><label class="f-child1 f-req" for="id-email">{!! __('Email') !!}</label></dt>
              <dd>
                <input required class="f-ctrl" id="id-email" type="text" name="email" value="{{ $p->email }}" maxlength="80" pattern=".+@.+" autofocus spellcheck="false" tabindex="1">
                <p class="f-child4 f-fhint">{!! __('Email info') !!}</p>
              </dd>
            </dl>
            <dl>
              <dt><label class="f-child1 f-req" for="id-username">{!! __('Username') !!}</label></dt>
              <dd>
                <input required class="f-ctrl" id="id-username" type="text" name="username" value="{{ $p->username }}" maxlength="25" pattern="^.{2,25}$" spellcheck="false" tabindex="2">
                <p class="f-child4 f-fhint">{!! __('Login format') !!}</p>
              </dd>
            </dl>
            <dl>
              <dt><label class="f-child1 f-req" for="id-password">{!! __('Passphrase') !!}</label></dt>
              <dd>
                <input required class="f-ctrl" id="id-password" type="password" name="password" pattern="^.{16,}$" tabindex="3">
                <p class="f-child4 f-fhint">{!! __('Pass format') !!} {!! __('Pass info') !!}</p>
              </dd>
            </dl>
          </fieldset>
          <p>
            <input class="f-btn" type="submit" name="register" value="{!! __('Sign up') !!}" tabindex="4">
          </p>
        </form>
      </div>
    </section>
