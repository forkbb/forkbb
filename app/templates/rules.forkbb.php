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
@if ($form = $p->form)
      <div class="f-fdiv f-lrdiv">
    @include('layouts/form')
      </div>
@endif
    </section>
