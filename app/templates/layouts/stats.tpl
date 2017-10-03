    <section class="f-stats">
      <h2>{!! __('Stats info') !!}</h2>
      <div class="clearfix">
@if($stats)
        <dl class="right">
          <dt>{!! __('Board stats') !!}</dt>
          <dd>{!! __('No of users') !!} <strong>{!! $stats['total_users'] !!}</strong></dd>
          <dd>{!! __('No of topics') !!} <strong>{!! $stats['total_topics'] !!}</strong></dd>
          <dd>{!! __('No of posts') !!} <strong>{!! $stats['total_posts'] !!}</strong></dd>
        </dl>
@endif
        <dl class="left">
          <dt>{!! __('User info') !!}</dt>
@if($stats && is_string($stats['newest_user']))
          <dd>{!! __('Newest user')  !!} {{ $stats['newest_user'] }}</dd>
@elseif($stats)
          <dd>{!! __('Newest user')  !!} <a href="{!! $stats['newest_user'][0] !!}">{{ $stats['newest_user'][1] }}</a></dd>
@endif
@if($online)
          <dd>{!! __('Visitors online', $online['number_of_users'], $online['number_of_guests']) !!}</dd>
@endif
@if($stats)
          <dd>{!! __('Most online', $online['max'], $online['max_time']) !!}</dd>
@endif
        </dl>
@if($online && $online['list'])
        <dl class="f-inline f-onlinelist"><!-- inline -->
          <dt>{!! __('Online users') !!}</dt>
@foreach($online['list'] as $cur)
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
