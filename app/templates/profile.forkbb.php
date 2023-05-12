@include ('layouts/crumbs')
@section ('avatar')<img class="f-avatar-img" src="{{ $p->curUser->avatar }}" alt="{{ $p->curUser->username }}"> @endsection
@section ('signature') @if ($p->signatureSection){!! $p->curUser->htmlSign !!} @endif @endsection
@extends ('layouts/main')
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __(['%s\'s profile', $p->curUser->username]) !!}</h1>
    </div>
    <div class="f-nav-links">
@yield ('crumbs')
@if ($p->actionBtns)
      <div class="f-nlinks-b">
        <div class="f-actions-links">
          <small>{!! __('ACTIONS') !!}</small>
    @foreach ($p->actionBtns as $key => $cur)
          <small>|</small>
          <span class="f-act-span"><a class="f-btn f-btn-{{ $key }}" href="{{ $cur[0] }}" title="{{ $cur[1] }}"><span>{{ $cur[1] }}</span></a></span>
    @endforeach
        </div>
      </div>
@endif
    </div>
@if ($form = $p->form)
    <div id="fork-profile" class="f-main">
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
    </div>
@endif
@if ($form = $p->formOAuth)
    <div id="fork-oauth" class="f-main">
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Add account') !!}</h2>
    @include ('layouts/form')
      </div>
    </div>
@endif
