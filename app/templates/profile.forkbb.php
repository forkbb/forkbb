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
@section ('avatar')<img class="f-avatar-img" src="{!! $p->curUser->avatar !!}" alt="{{ $p->curUser->username }}"> @endsection
@section ('signature'){!! $p->curUser->htmlSign !!} @endsection
@extends ('layouts/main')
    <div class="f-nav-links">
@yield ('crumbs')
@if ($p->actionBtns)
      <div class="f-nlinks-b">
        <div class="f-actions-links">
    @foreach ($p->actionBtns as $key => $cur)
          <a class="f-btn f-btn-{{ $key }}" href="{!! $cur[0] !!}" title="{{ $cur[1] }}">{{ $cur[1] }}</a>
    @endforeach
        </div>
      </div>
@endif
    </div>
@if ($form = $p->form)
    <section class="f-main f-profile">
      <h2>{!! $p->title !!}</h2>
      <div class="f-fdiv">
    @include ('layouts/form')
      </div>
    </section>
@endif
