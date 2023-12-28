@section ('crumbs')
      <!-- PRE start -->
      <nav class="f-nav-crumbs">
        <ol class="f-crumbs" itemscope itemtype="https://schema.org/BreadcrumbList">
    @foreach ($p->crumbs as $cur)
          <!-- PRE foreachStart -->
        @if (\is_object($cur[0]))
          <li @class(['f-crumb', 'f-subscribed' => $cur[0]->is_subscribed, [$cur[3], 'f-cr-']]) itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"><!-- inline -->
            <a @class(['f-crumb-a', 'active' => $cur[4], [$cur[3], 'f-cr-', '-a']]) @if ($cur[4]) aria-current="page" @endif href="{{ $cur[0]->link }}" title="{!! __($cur[2] ?? $cur[1]) !!}" itemprop="item">
              <span itemprop="name">{!! __($cur[1]) !!}</span>
            </a>
            @if ($cur[5])
            &nbsp;[&nbsp;<a href="{{ $cur[5][0] }}">{{ $cur[5][1] }}</a>&nbsp;]
            @endif
            <meta itemprop="position" content="{!! @iteration !!}">
          </li><!-- endinline -->
        @else
          <li @class(['f-crumb', [$cur[3], 'f-cr-']]) itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"><!-- inline -->
            @if ($cur[0])
            <a @class(['f-crumb-a', 'active' => $cur[4], [$cur[3], 'f-cr-', '-a']]) @if ($cur[4]) aria-current="page" @endif href="{{ $cur[0] }}" title="{!! __($cur[2] ?? $cur[1]) !!}" itemprop="item">
              <span itemprop="name">{!! __($cur[1]) !!}</span>
            </a>
            @else
            <span @if ($cur[4]) class="active" @endif itemprop="name">{!! __($cur[1]) !!}</span>
            @endif
            @if ($cur[5])
            &nbsp;[&nbsp;<a href="{{ $cur[5][0] }}">{{ $cur[5][1] }}</a>&nbsp;]
            @endif
            <meta itemprop="position" content="{!! @iteration !!}">
          </li><!-- endinline -->
        @endif
          <!-- PRE foreachEnd -->
    @endforeach
        </ol>
      </nav>
      <!-- PRE end -->
@endsection
