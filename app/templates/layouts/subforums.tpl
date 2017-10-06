@foreach($cat['forums'] as $cur)
@if($cur['redirect_url'])
            <li id="forum-{!! $cur['fid']!!}" class="f-row f-fredir">
              <div class="f-cell f-cmain">
                <div class="f-ficon"></div>
                <div class="f-finfo">
                  <h3><span class="f-fredirtext">{!! __('Link to') !!}</span> <a class="f-ftname" href="{!! $cur['redirect_url'] !!}">{{ $cur['forum_name'] }}</a></h3>
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
                    <a class="f-ftname" href="{!! $cur['forum_link'] !!}">{{ $cur['forum_name'] }}</a>
@if($cur['new'])
                    <span class="f-newtxt"><a href="">{!! __('New posts') !!}</a></span>
@endif
                  </h3>
@if($cur['subforums'])
                  <dl class="f-inline f-fsub"><!-- inline -->
                    <dt>{!! __('Sub forum', count($cur['subforums'])) !!}</dt>
@foreach($cur['subforums'] as $sub)
                    <dd><a href="{!! $sub[0] !!}">{{ $sub[1] }}</a></dd>
@endforeach
                  </dl><!-- endinline -->
@endif
@if($cur['forum_desc'])
                  <p class="f-fdesc">{!! $cur['forum_desc'] !!}</p>
@endif
@if($cur['moderators'])
                  <dl class="f-inline f-modlist"><!-- inline -->
                    <dt>{!! __('Moderated by', count($cur['moderators'])) !!}</dt>
@foreach($cur['moderators'] as $mod)
@if(is_string($mod))
                    <dd>{{ $mod }}</dd>
@else
                    <dd><a href="{!! $mod[0] !!}">{{ $mod[1] }}</a></dd>
@endif
@endforeach
                  </dl><!-- endinline -->
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
