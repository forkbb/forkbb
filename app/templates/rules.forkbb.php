@section ('crumbs')
      <ul class="f-crumbs">
    @foreach ($p->crumbs as $cur)
        <li class="f-crumb"><!-- inline -->
        @if ($cur[0])
          <a href="{!! $cur[0] !!}" @if ($cur[2]) class="active" @endif>{{ $cur[1] }}</a>
        @else
          <span @if ($cur[2]) class="active" @endif>{{ $cur[1] }}</span>
        @endif
        </li><!-- endinline -->
    @endforeach
      </ul>
@endsection
@extends ('layouts/main')
    <div class="f-nav-links">
@yield ('crumbs')
    </div>
    <section class="f-main f-rules">
      <h2>{!! $p->title !!}</h2>
      <div id="id-rules">{!! $p->rules !!}</div>
@if ($p->formAction)
      <div class="f-fdiv f-lrdiv">
        <form class="f-form" method="post" action="{!! $p->formAction !!}">
          <input type="hidden" name="token" value="{!! $p->formToken !!}">
          <fieldset>
            <dl>
              <dt></dt>
              <dd><label class="f-child2"><input type="checkbox" name="agree" value="{!! $p->formHash !!}" tabindex="1">{!! __('Agree') !!}</label></dd>
            </dl>
          </fieldset>
          <p class="f-btns">
            <input class="f-btn" type="submit" name="register" value="{!! __('Register') !!}" tabindex="2">
          </p>
        </form>
      </div>
@endif
    </section>
