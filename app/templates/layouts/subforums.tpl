@foreach($forums as $cur)
@if($cur->redirect_url)
            <li id="forum-{!! $cur->id !!}" class="f-row f-fredir">
              <div class="f-cell f-cmain">
                <div class="f-ficon"></div>
                <div class="f-finfo">
                  <h3><span class="f-fredirtext">{!! __('Link to') !!}</span> <a class="f-ftname" href="{!! $cur->redirect_url !!}">{{ $cur->forum_name }}</a></h3>
@if($cur->forum_desc)
                  <p class="f-fdesc">{!! $cur->forum_desc !!}</p>
@endif
                </div>
              </div>
            </li>
@else
@if($cur->tree->newMessages)
            <li id="forum-{!! $cur->id !!}" class="f-row f-fnew">
@else
            <li id="forum-{!! $cur->id !!}" class="f-row">
@endif
              <div class="f-cell f-cmain">
                <div class="f-ficon"></div>
                <div class="f-finfo">
                  <h3>
                    <a class="f-ftname" href="{!! $cur->link !!}">{{ $cur->forum_name }}</a>
@if($cur->tree->newMessages)
                    <span class="f-newtxt"><a href="">{!! __('New posts') !!}</a></span>
@endif
                  </h3>
@if($cur->subforums)
                  <dl class="f-inline f-fsub"><!-- inline -->
                    <dt>{!! __('Sub forum', count($cur->subforums)) !!}</dt>
@foreach($cur->subforums as $sub)
                    <dd><a href="{!! $sub->link !!}">{{ $sub->forum_name }}</a></dd>
@endforeach
                  </dl><!-- endinline -->
@endif
@if($cur->forum_desc)
                  <p class="f-fdesc">{!! $cur->forum_desc !!}</p>
@endif
@if($cur->moderators)
                  <dl class="f-inline f-modlist"><!-- inline -->
                    <dt>{!! __('Moderated by', count($cur->moderators)) !!}</dt>
@foreach($cur->moderators as $mod)
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
                  <li>{!! __('%s Topic', $cur->tree->num_topics, $cur->tree->num()->num_topics) !!}</li>
                  <li>{!! __('%s Post', $cur->tree->num_posts, $cur->tree->num()->num_posts)!!}</li>
                </ul>
              </div>
              <div class="f-cell f-clast">
                <ul>
@if($cur->tree->last_post_id)
                  <li class="f-cltopic"><a href="{!! $cur->tree->linkLast !!}" title="&quot;{{ $cur->tree->cens()->last_topic }}&quot; - {!! __('Last post') !!}">{{ $cur->tree->cens()->last_topic }}</a></li>
                  <li class="f-clposter">{!! __('by') !!} {{ $cur->tree->last_poster }}</li>
                  <li class="f-cltime">{!! $cur->tree->dt()->last_post !!}</li>
@else
                  <li class="f-cltopic">{!! __('Never') !!}</li>
@endif
                </ul>
              </div>
            </li>
@endif
@endforeach
