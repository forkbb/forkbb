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
@if ($p->parser)
    <section class="f-main f-preview">
      <h2>{!! __('Post preview') !!}</h2>
      <div class="f-post-body clearfix">
        <div class="f-post-right f-post-main">
          {!! $p->parser->getHtml() !!}
        </div>
      </div>
    </section>
@endif
@if ($form = $p->form)
    <section class="post-form">
      <h2>{!! $p->titleForm !!}</h2>
      <div class="f-fdiv">
  @include ('layouts/form')
      </div>
    </section>
@endif
