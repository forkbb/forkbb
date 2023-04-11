    <aside id="fork-stats">
      <p class="f-sim-header">{!! __('Stats info') !!}</p>
@if ($p->stats)
      <dl id="fork-stboard">
        <dt class="f-stats-dt">{!! __('Board stats') !!}</dt>
        <dd class="f-stats-dd">{!! __(['No of users: %s', num($p->stats->userTotal)]) !!}</dd>
        <dd class="f-stats-dd">{!! __(['No of topics: %s', num($p->stats->topicTotal)]) !!}</dd>
        <dd class="f-stats-dd">{!! __(['No of posts: %s', num($p->stats->postTotal)]) !!}</dd>
      </dl>
@endif
      <dl id="fork-stusers">
        <dt class="f-stats-dt">{!! __('User info') !!}</dt>
@if ($p->stats)
    @if ($p->stats->userLast['link'])
        <dd class="f-stats-dd">{!! __(['Newest user: <a href="%2$s">%1$s</a>', $p->stats->userLast['name'], $p->stats->userLast['link']]) !!}</dd>
    @else
        <dd class="f-stats-dd">{!! __(['Newest user: %s', $p->stats->userLast['name']]) !!}</dd>
    @endif
@endif
@if ($p->online)
        <dd class="f-stats-dd">{!! __(['Visitors online', num($p->online->numUsers), num($p->online->numGuests)]) !!}</dd>
    @if ($p->stats)
        <dd class="f-stats-dd">{!! __(['Most online', num($p->online->maxNum), dt($p->online->maxTime)]) !!}</dd>
    @endif
@endif
      </dl>
@if ($p->online && $p->online->info)
      <dl id="fork-onlinelist" class="f-inline"><!-- inline -->
        <dt id="id-onlst-dt">{!! __('Online users') !!}</dt>
    @foreach ($p->online->info as $cur)
        @if ($cur['link'])
        <dd><a href="{{ $cur['link'] }}">{{ $cur['name'] }}</a></dd>
        @else
        <dd>{{ $cur['name'] }}</dd>
        @endif
    @endforeach
      </dl><!-- endinline -->
@endif
    </aside>
