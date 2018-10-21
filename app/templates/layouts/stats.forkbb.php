    <section class="f-stats">
      <h2>{!! __('Stats info') !!}</h2>
@if ($p->stats)
      <dl class="f-stboard">
        <dt>{!! __('Board stats') !!}</dt>
        <dd>{!! __('No of users') !!} <b>{!! num($p->stats->userTotal) !!}</b></dd>
        <dd>{!! __('No of topics') !!} <b>{!! num($p->stats->topicTotal) !!}</b></dd>
        <dd>{!! __('No of posts') !!} <b>{!! num($p->stats->postTotal) !!}</b></dd>
      </dl>
@endif
      <dl class="f-stusers">
        <dt>{!! __('User info') !!}</dt>
@if ($p->stats)
    @if (\is_string($p->stats->userLast))
        <dd>{!! __('Newest user')  !!} {{ $p->stats->userLast }}</dd>
    @else
        <dd>{!! __('Newest user')  !!} <a href="{!! $p->stats->userLast[0] !!}">{{ $p->stats->userLast[1] }}</a></dd>
    @endif
@endif
@if ($p->online)
        <dd>{!! __('Visitors online', num($p->online->numUsers), num($p->online->numGuests)) !!}</dd>
@endif
@if ($p->stats)
        <dd>{!! __('Most online', num($p->online->maxNum), dt($p->online->maxTime)) !!}</dd>
@endif
      </dl>
@if ($p->online && $p->online->info)
      <dl class="f-inline f-onlinelist"><!-- inline -->
        <dt>{!! __('Online users') !!}</dt>
    @foreach ($p->online->info as $cur)
        @if (\is_string($cur))
        <dd>{{ $cur }}</dd>
        @else
        <dd><a href="{!! $cur[0] !!}">{{ $cur[1] }}</a></dd>
        @endif
    @endforeach
      </dl><!-- endinline -->
@endif
    </section>
