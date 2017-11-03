    <section class="f-stats">
      <h2>{!! __('Stats info') !!}</h2>
      <div class="clearfix">
@if($p->stats)
        <dl class="right">
          <dt>{!! __('Board stats') !!}</dt>
          <dd>{!! __('No of users') !!} <strong>{!! $p->stats['total_users'] !!}</strong></dd>
          <dd>{!! __('No of topics') !!} <strong>{!! $p->stats['total_topics'] !!}</strong></dd>
          <dd>{!! __('No of posts') !!} <strong>{!! $p->stats['total_posts'] !!}</strong></dd>
        </dl>
@endif
        <dl class="left">
          <dt>{!! __('User info') !!}</dt>
@if($p->stats && is_string($p->stats['newest_user']))
          <dd>{!! __('Newest user')  !!} {{ $p->stats['newest_user'] }}</dd>
@elseif($p->stats)
          <dd>{!! __('Newest user')  !!} <a href="{!! $p->stats['newest_user'][0] !!}">{{ $p->stats['newest_user'][1] }}</a></dd>
@endif
@if($p->online)
          <dd>{!! __('Visitors online', $p->online['number_of_users'], $p->online['number_of_guests']) !!}</dd>
@endif
@if($p->stats)
          <dd>{!! __('Most online', $p->online['max'], $p->online['max_time']) !!}</dd>
@endif
        </dl>
@if($p->online && $p->online['list'])
        <dl class="f-inline f-onlinelist"><!-- inline -->
          <dt>{!! __('Online users') !!}</dt>
@foreach($p->online['list'] as $cur)
@if(is_string($cur))
          <dd>{{ $cur }}</dd>
@else
          <dd><a href="{!! $cur[0] !!}">{{ $cur[1] }}</a></dd>
@endif
@endforeach
        </dl><!-- endinline -->
@endif
      </div>
    </section>
