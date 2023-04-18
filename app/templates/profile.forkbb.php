@include ('layouts/crumbs')
@section ('avatar')<img class="f-avatar-img" src="{{ $p->curUser->avatar }}" alt="{{ $p->curUser->username }}"> @endsection
@section ('signature') @if ($p->signatureSection){!! $p->curUser->htmlSign !!} @endif @endsection
@extends ('layouts/main')
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
    <section id="fork-profile" class="f-main">
      <h2>{!! __(['%s\'s profile', $p->curUser->username]) !!}</h2>
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
    </section>
@endif
