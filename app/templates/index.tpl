@extends('layouts/main')
@if($forums)
    <section class="f-main">
      <ol class="f-forumlist">
@foreach($forums as $id => $cat)
        <li id="cat-{!! $id !!}" class="f-category">
          <h2>{{ $cat['name'] }}</h2>
          <ol class="f-table">
            <li class="f-row f-thead" value="0">
              <div class="f-hcell f-cmain">{!! __('Forum') !!}</div>
              <div class="f-hcell f-cstats">{!! __('Stats') !!}</div>
              <div class="f-hcell f-clast">{!! __('Last post') !!}</div>
            </li>
@foreach($cat['forums'] as $cur)
@if($cur['redirect_url'])
            <li id="forum-{!! $cur['fid']!!}" class="f-row f-fredir">
              <div class="f-cell f-cmain">
                <div class="f-ficon"></div>
                <div class="f-finfo">
                  <h3><span class="f-fredirtext">{!! __('Link to') !!}</span> <a href="{!! $cur['redirect_url'] !!}">{{ $cur['forum_name'] }}</a></h3>
@if($cur['forum_desc'])
                  <p class="f-fdesc">{!! $cur['forum_desc'] !!}</p>
@endif
                </div>
              </div>
            </li>
@else
@if($cur['new'])
            <li id="forum-{!! $cur['fid'] !!}" class="f-row f-fnew">
@else
            <li id="forum-{!! $cur['fid'] !!}" class="f-row">
@endif
              <div class="f-cell f-cmain">
                <div class="f-ficon"></div>
                <div class="f-finfo">
                  <h3>
                    <a href="{!! $cur['forum_link'] !!}">{{ $cur['forum_name'] }}</a>
@if($cur['new'])
                    <span class="f-newtxt"><a href="">{!! __('New posts') !!}</a></span>
@endif
                  </h3>
@if($cur['subforums'])
                  <dl class="f-inline f-fsub"><!--inline-->
                    <dt>{!! __('Sub forum', count($cur['subforums'])) !!}</dt>
@foreach($cur['subforums'] as $sub)
                    <dd><a href="{!! $sub[0] !!}">{{ $sub[1] }}</a></dd>
@endforeach
                  </dl><!--endinline-->
@endif
@if($cur['forum_desc'])
                  <p class="f-fdesc">{!! $cur['forum_desc'] !!}</p>
@endif
@if($cur['moderators'])
                  <dl class="f-inline f-modlist"><!--inline-->
                    <dt>{!! __('Moderated by') !!}</dt>
@foreach($cur['moderators'] as $mod)
@if(is_string($mod))
                    <dd>{{ $mod }}</dd>
@else
                    <dd><a href="{!! $mod[0] !!}">{{ $mod[1] }}</a></dd>
@endif
@endforeach
                  </dl><!--endinline-->
@endif
                </div>
              </div>
              <div class="f-cell f-cstats">
                <ul>
                  <li>{!! __('%s Topic', $cur['num_topics'], $cur['topics']) !!}</li>
                  <li>{!! __('%s Post', $cur['num_posts'], $cur['posts'])!!}</li>
                </ul>
              </div>
              <div class="f-cell f-clast">
                <ul>
@if($cur['last_post_id'])
                  <li class="f-cltopic"><a href="{!! $cur['last_post_id'] !!}" title="&quot;{{ $cur['last_topic'] }}&quot; - {!! __('Last post') !!}">{{ $cur['last_topic'] }}</a></li>
                  <li class="f-clposter">{!! __('by') !!} {{ $cur['last_poster'] }}</li>
                  <li class="f-cltime">{!! $cur['last_post'] !!}</li>
@else
                  <li class="f-cltopic">{!! __('Never') !!}</li>
@endif
                </ul>
              </div>
            </li>
@endif
@endforeach
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
        <dl class="f-inline f-onlinelist"><!--inline-->
          <dt>{!! __('Online') !!}</dt>
@foreach($online['list'] as $cur)
@if(is_string($cur))
          <dd>{{ $cur }}</dd>
@else
          <dd><a href="{!! $cur[0] !!}">{{ $cur[1] }}</a></dd>
@endif
@endforeach
        </dl><!--endinline-->
@endif
      </div>
    </section>
