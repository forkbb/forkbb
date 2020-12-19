@section ('crumbs')
      <ul class="f-crumbs">
    @foreach ($p->crumbs as $cur)
        @if (\is_object($cur[0]))
        <li class="f-crumb @if ($cur[0]->is_subscribed) f-subscribed @endif"><!-- inline -->
          <a href="{{ $cur[0]->link }}" @if ($cur[2]) class="active" @endif>{{ $cur[1] }}</a>
        </li><!-- endinline -->
        @else
        <li class="f-crumb"><!-- inline -->
            @if ($cur[0])
          <a href="{{ $cur[0] }}" @if ($cur[2]) class="active" @endif>{{ $cur[1] }}</a>
            @else
          <span @if ($cur[2]) class="active" @endif>{{ $cur[1] }}</span>
            @endif
        </li><!-- endinline -->
        @endif
    @endforeach
      </ul>
@endsection
