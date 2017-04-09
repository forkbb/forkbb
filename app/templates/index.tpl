@extends('layouts/main')
@if($forums)
    <section class="f-main">
      <ol class="f-ftlist">
@foreach($forums as $id => $cat)
        <li id="cat-{!! $id !!}" class="f-category">
          <h2>{{ $cat['name'] }}</h2>
          <ol class="f-table">
            <li class="f-row f-thead" value="0">
              <div class="f-hcell f-cmain">{!! __('Forum') !!}</div>
              <div class="f-hcell f-cstats">{!! __('Stats') !!}</div>
              <div class="f-hcell f-clast">{!! __('Last post') !!}</div>
            </li>
@include('layouts/subforums')
          </ol>
        </li>
@endforeach
      </ol>
    </section>
@else
    <section class="f-main f-message">
      <h2>{!! __('Empty board') !!}</h2>
    </section>
@endif
    <section class="f-stats">
      <h2>{!! __('Board info') !!}</h2>
      <div class="clearfix">
        <dl class="right">
          <dt>{!! __('Board stats') !!}</dt>
          <dd>{!! __('No of users') !!} <strong>{!! $stats['total_users'] !!}</strong></dd>
          <dd>{!! __('No of topics') !!} <strong>{!! $stats['total_topics'] !!}</strong></dd>
          <dd>{!! __('No of posts') !!} <strong>{!! $stats['total_posts'] !!}</strong></dd>
        </dl>
        <dl class="left">
          <dt>{!! __('User info') !!}</dt>
@if(is_string($stats['newest_user']))
          <dd>{!! __('Newest user')  !!} {{ $stats['newest_user'] }}</dd>
@else
          <dd>{!! __('Newest user')  !!} <a href="{!! $stats['newest_user'][0] !!}">{{ $stats['newest_user'][1] }}</a></dd>
@endif
@if($online)
          <dd>{!! __('Users online') !!} <strong>{!! $online['number_of_users'] !!}</strong>, {!! __('Guests online') !!} <strong>{!! $online['number_of_guests'] !!}</strong>.</dd>
          <dd>{!! __('Most online', $online['max'], $online['max_time']) !!}</dd>
@endif
        </dl>
@if($online && $online['list'])
        <dl class="f-inline f-onlinelist"><!-- inline -->
          <dt>{!! __('Online') !!}</dt>
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
