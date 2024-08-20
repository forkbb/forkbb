@include ('layouts/crumbs')
@section ('avatar')<img class="f-avatar-img" src="{{ $p->curUser->avatar }}" alt="{{ $p->curUser->username }}"> @endsection
@section ('signature') @if ($p->signatureSection){!! $p->curUser->htmlSign !!} @endif @endsection
@section ('about_me') @if ($p->aboutMePost)<div class="f-post-body"><div class="f-post-main">{!! $p->aboutMePost->html() !!}</div></div> @endif @endsection
@extends ('layouts/main')
    <!-- PRE start -->
    <!-- PRE h1Before -->
    <div class="f-mheader">
      <h1 id="fork-h1">{!! __(['%s\'s profile', $p->curUser->username]) !!}</h1>
    </div>
    <!-- PRE h1After -->
    <!-- PRE linksBefore -->
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
    <!-- PRE linksAfter -->
@if ($form = $p->form)
    <!-- PRE mainBefore -->
    <section id="fork-profile{{ $p->profileIdSuffix or '' }}" class="f-main f-main-profile">
      <!-- PRE mainStart -->
      <h2>@if ($p->profileHeader === $p->curUser->username){{ $p->profileHeader }} @else{!! __($p->profileHeader) !!} @endif</h2>
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
      <!-- PRE mainEnd -->
    </section>
    <!-- PRE mainAfter -->
@endif
@if ($form = $p->formOAuth)
    <!-- PRE oauthBefore -->
    <div id="fork-oauth" class="f-main">
      <!-- PRE oauthStart -->
      <div class="f-fdiv f-lrdiv">
        <h2>{!! __('Add account') !!}</h2>
    @include ('layouts/form')
      </div>
      <!-- PRE oauthEnd -->
    </div>
    <!-- PRE oauthAfter -->
@endif
    <!-- PRE end -->
