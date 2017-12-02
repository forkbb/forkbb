@extends ('layouts/main')
    <section class="f-main f-login">
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Passphrase reset') !!}</h2>
        <form class="f-form" method="post" action="{!! $p->formAction !!}">
          <input type="hidden" name="token" value="{!! $p->formToken !!}">
          <fieldset>
            <dl>
              <dt><label class="f-child1 f-req" for="id-email">{!! __('Email') !!}</label></dt>
              <dd>
                <input required class="f-ctrl" id="id-email" type="text" name="email" value="{{ $p->email }}" maxlength="80" pattern=".+@.+" autofocus spellcheck="false" tabindex="1">
                <p class="f-child4">{!! __('Passphrase reset info') !!}</p>
              </dd>
            </dl>
          </fieldset>
          <p>
            <input class="f-btn" type="submit" name="submit" value="{!! __('Send email') !!}" tabindex="2">
          </p>
        </form>
      </div>
    </section>
