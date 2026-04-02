@section ('pagination')
    @if ($p->pagination)
        @if (\max(\array_map(function ($cur) {return \is_int($cur[1]) ? $cur[1] : 0;}, $p->pagination)) > 6)
        <nav class="f-pages f-pages-disc">
        @else
        <nav class="f-pages">
        @endif
        @foreach ($p->pagination as $cur)
            @if (true === $cur[2])
          <a class="f-page active" href="{{ $cur[0] }}">{!! (int) $cur[1] !!}</a>
            @elseif ('info' === $cur[1])
          <span class="f-pinfo">{!! __($cur[0]) !!}</span>
            @elseif ('space' === $cur[1])
          <span class="f-page f-pspacer">{!! __('Spacer') !!}</span>
            @elseif ('prev' === $cur[1])
          <a rel="prev" class="f-page f-pprev" href="{{ $cur[0] }}" title="{{ __('Previous') }}"><span>{!! __('Previous') !!}</span></a>
            @elseif ('next' === $cur[1])
          <a rel="next" class="f-page f-pnext" href="{{ $cur[0] }}" title="{{ __('Next') }}"><span>{!! __('Next') !!}</span></a>
            @else
          <a class="f-page @if (null === $cur[2]) @if (1 === $cur[1]) f-pfirst @else f-plast @endif @endif" href="{{ $cur[0] }}">{!! (int) $cur[1] !!}</a>
            @endif
        @endforeach
        </nav>
    @endif
@endsection
@extends ('layouts/admin')
@if ($p->pagination)
      <div class="f-nav-links">
        <div class="f-nlinks-b">
    @yield ('pagination')
        </div>
      </div>
@endif
      <section id="fork-ausersrch-rs" class="f-admin">
        <h2>{!! __('Results head') !!}</h2>
        <div class="f-fdiv">
@if ($form = $p->formResult)
    @include ('layouts/form')
@endif
        </div>
      </section>
@if ($p->pagination)
      <div class="f-nav-links">
        <div class="f-nlinks">
    @yield ('pagination')
        </div>
      </div>
@endif
