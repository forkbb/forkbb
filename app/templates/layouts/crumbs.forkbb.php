@section ('crumbs')
      <nav>
        <ol class="f-crumbs" itemscope itemtype="https://schema.org/BreadcrumbList">
    @foreach ($p->crumbs as $cur)
        @if (\is_object($cur[0]))
          <li class="f-crumb @if ($cur[0]->is_subscribed) f-subscribed @endif" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"><!-- inline -->
            <a class="f-crumb-a @if ($cur[2]) active" aria-current="page @endif" href="{{ $cur[0]->link }}" itemprop="item">
              <span itemprop="name">{!! __($cur[1]) !!}</span>
            </a>
            <meta itemprop="position" content="{!! @iteration !!}">
          </li><!-- endinline -->
        @else
          <li class="f-crumb" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"><!-- inline -->
            @if ($cur[0])
            <a class="f-crumb-a @if ($cur[2]) active" aria-current="page @endif" href="{{ $cur[0] }}" itemprop="item">
              <span itemprop="name">{!! __($cur[1]) !!}</span>
            </a>
            @else
            <span @if ($cur[2]) class="active" @endif itemprop="name">{!! __($cur[1]) !!}</span>
            @endif
            <meta itemprop="position" content="{!! @iteration !!}">
          </li><!-- endinline -->
        @endif
    @endforeach
        </ol>
      </nav>
@endsection
