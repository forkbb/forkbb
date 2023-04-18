@include ('layouts/crumbs')
@section ('pagination')
    @if ($p->model->pagination)
        <nav class="f-pages">
        @foreach ($p->model->pagination as $cur)
            @if ($cur[2])
          <a class="f-page active" href="{{ $cur[0] }}">{{ $cur[1] }}</a>
            @elseif ('info' === $cur[1])
          <span class="f-pinfo">{!! __($cur[0]) !!}</span>
            @elseif ('space' === $cur[1])
          <span class="f-page f-pspacer">{!! __('Spacer') !!}</span>
            @elseif ('prev' === $cur[1])
          <a rel="prev" class="f-page f-pprev" href="{{ $cur[0] }}" title="{!! __('Previous') !!}"><span>{!! __('Previous') !!}</span></a>
            @elseif ('next' === $cur[1])
          <a rel="next" class="f-page f-pnext" href="{{ $cur[0] }}" title="{!! __('Next') !!}"><span>{!! __('Next') !!}</span></a>
            @else
          <a class="f-page" href="{{ $cur[0] }}">{{ $cur[1] }}</a>
            @endif
        @endforeach
        </nav>
    @endif
@endsection
@extends ('layouts/main')
    <div class="f-mheader">
    @if (\is_array($p->model->name))
      <h1 id="fork-h1">{!! __($p->model->name) !!}</h1>
    @else
      <h1 id="fork-h1">{{ $p->model->forum_name or $p->model->name }}</h1>
    @endif
    @if ('' != $p->model->forum_desc)
      <p class="f-fdesc">{!! $p->model->forum_desc !!}</p>
    @endif
    </div>
@if ($forums = $p->model->subforums)
    <div class="f-nav-links">
    @yield ('crumbs')
    </div>
    <section id="fork-subforums">
      <ol class="f-ftlist">
        <li id="id-subforums{{ $p->model->id }}" class="f-category">
          <h2 class="f-ftch2">{{ __(['Sub forum', 2]) }}</h2>
          <ol class="f-table">
            <li hidden class="f-row f-thead" value="0">
              <div class="f-hcell f-cmain">{!! __(['Sub forum', 1]) !!}</div>
              <div class="f-hcell f-cstats">{!! __('Stats') !!}</div>
              <div class="f-hcell f-clast">{!! __('Last post') !!}</div>
            </li>
    @include ('layouts/subforums')
          </ol>
        </li>
      </ol>
    </section>
@endif
    <div class="f-nav-links">
@yield ('crumbs')
@if ($p->model->canCreateTopic || $p->model->pagination)
      <div class="f-nlinks-b">
    @yield ('pagination')
    @if ($p->model->canCreateTopic)
        <div class="f-actions-links">
          <small>{!! __('ACTIONS') !!}</small>
          <small>|</small>
          <span class="f-act-span"><a class="f-btn f-btn-create-topic" title="{{ __('Post topic') }}" href="{{ $p->model->linkCreateTopic }}"><span>{!! __('Post topic') !!}</span></a></span>
        </div>
    @endif
      </div>
@endif
    </div>
@if ($p->topics)
    <section id="fork-forum" class="f-main">
      <h2>{!! __('Topic list') !!}</h2>
      <div class="f-ftlist">
        <ol class="f-table">
          <li hidden class="f-row f-thead" value="0">
            <div class="f-hcell f-cmain">{!! __(['Topic', 1]) !!}</div>
            <div class="f-hcell f-cstats">{!! __('Stats') !!}</div>
            <div class="f-hcell f-clast">{!! __('Last post') !!}</div>
          </li>
    @foreach ($p->topics as $id => $topic)
        @if (empty($topic->id) && $iswev = ['e' => [['Topic %s was not found in the database', $id]]])
          <li id="topic-{{ $id }}" class="f-row">
            @include ('layouts/iswev')
          </li>
        @elseif ($topic->moved_to)
          <li id="topic-{{ $topic->id }}" class="f-row f-fredir">
            <div class="f-cell f-cmain">
            @if ($p->enableMod)
              <input hidden id="checkbox-{{ $topic->id }}" class="f-fch" type="checkbox" name="ids[{{ $topic->id }}]" value="{{ $topic->id }}" form="id-form-mod">
              <label class="f-ficon" for="checkbox-{{ $topic->id }}" title="{{ __('Select for moderation') }}"></label>
            @else
              <div class="f-ficon"></div>
            @endif
              <div class="f-finfo">
                <h3 class="f-finfo-h3">
                  <span class="f-tmoved" title="{{ __('Moved') }}"><small class="f-movedtxt">{!! __('Moved') !!}</small></span>
                  <a class="f-ftname" href="{{ $topic->link }}">{{ $topic->name }}</a>
                </h3>
              </div>
            </div>
          </li>
        @else
          <li id="topic-{{ $topic->id }}" class="f-row @if (false !== $topic->hasNew) f-fnew @endif @if (false !== $topic->hasUnread) f-funread @endif @if ($topic->sticky) f-fsticky @endif @if ($topic->closed) f-fclosed @endif @if ($topic->poll_type) f-fpoll @endif @if ($topic->dot) f-fposted @endif">
            <div class="f-cell f-cmain">
            @if ($p->enableMod)
              <input hidden id="checkbox-{{ $topic->id }}" class="f-fch" type="checkbox" name="ids[{{ $topic->id }}]" value="{{ $topic->id }}" form="id-form-mod">
              <label class="f-ficon" for="checkbox-{{ $topic->id }}" title="{{ __('Select for moderation') }}"></label>
            @else
              <div class="f-ficon"></div>
            @endif
              <div class="f-finfo">
                <h3 class="f-finfo-h3">
            @if ($topic->dot)
                  <span class="f-tdot"><span class="f-dottxt">·</span></span>
            @endif
            @if ($topic->sticky)
                  <span class="f-tsticky" title="{{ __('Sticky') }}"><small class="f-stickytxt">{!! __('Sticky') !!}</small></span>
            @endif
            @if ($topic->closed)
                  <span class="f-tclosed" title="{{ __('Closed') }}"><small class="f-closedtxt">{!! __('Closed') !!}</small></span>
            @endif
            @if ($topic->poll_type > 0)
                  <span class="f-tpoll" title="{{ __('Poll') }}"><small class="f-polltxt">{!! __('Poll') !!}</small></span>
            @endif
                  <a class="f-ftname" href="{{ $topic->link }}">{{ $topic->name }}</a>
            @if ($topic->pagination)
                  <span class="f-tpages">
                @foreach ($topic->pagination as $cur)
                    @if ('space' === $cur[1])
                    <span class="f-page f-pspacer">{!! __('Spacer') !!}</span>
                    @else
                    <a class="f-page" href="{{ $cur[0] }}">{{ $cur[1] }}</a>
                    @endif
                @endforeach
                  </span>
            @endif
            @if (false !== $topic->hasUnread || false !== $topic->hasNew)
                  <small>(</small>
            @endif
            @if (false !== $topic->hasUnread)
                  <span class="f-tunread"><a href="{{ $topic->linkUnread }}" title="{{ __('Unread posts info') }}"><small class="f-unreadtxt">{!! __('Unread posts') !!}</small></a></span>
            @endif
            @if (false !== $topic->hasNew)
                  <span class="f-tnew"><a href="{{ $topic->linkNew }}" title="{{ __('New posts info') }}"><small class="f-newtxt">{!! __('New posts') !!}</small></a></span>
            @endif
            @if (false !== $topic->hasUnread || false !== $topic->hasNew)
                  <small>)</small>
            @endif
                </h3>
                <p class="f-finfo-p">
                  <span class="f-cmposter">{!! __(['by %s', $topic->poster]) !!}</span>
                  <span class="f-cmposted">{{ dt($topic->posted) }}</span>
            @if ($p->searchMode)
                  <span class="f-cmforum"><a href="{{ $topic->parent->link }}">{{ $topic->parent->forum_name }}</a></span>
            @endif
                </p>
              </div>
            </div>
            <div class="f-cell f-cstats">
              <span>{!! __(['%s Reply', $topic->num_replies, num($topic->num_replies)]) !!}</span>
            @if ($topic->showViews)
              <small>·</small>
              <span>{!! __(['%s View', $topic->num_views, num($topic->num_views)]) !!}</span>
            @endif
            </div>
            <div class="f-cell f-clast">
              <span class="f-cltopic">{!! __(['Last post <a href="%1$s">%2$s</a>', $topic->linkLast, dt($topic->last_post)]) !!}</span>
              <span class="f-clposter">{!! __(['by %s', $topic->last_poster]) !!}</span>
            </div>
          </li>
        @endif
    @endforeach
        </ol>
      </div>
    </section>
    <div class="f-nav-links">
    @if ($p->model->canCreateTopic || $p->model->pagination || $p->model->canMarkRead || $p->model->canSubscription)
      <div class="f-nlinks-a">
        @if ($p->model->canCreateTopic || $p->model->canMarkRead || $p->model->canSubscription)
        <div class="f-actions-links">
          <small>{!! __('ACTIONS') !!}</small>
            @if ($p->model->canMarkRead)
          <small>|</small>
          <span class="f-act-span"><a class="f-btn f-btn-markread f-opacity" title="{{ __('Mark forum read') }}" href="{{ $p->model->linkMarkRead }}"><span>{!! __('All is read') !!}</span></a></span>
            @endif
            @if ($p->model->canSubscription)
          <small>|</small>
                @if ($p->model->is_subscribed)
          <span class="f-act-span"><a class="f-btn f-btn-unsubscribe f-opacity" title="{{ __('Unsubscribe forum') }}" href="{{ $p->model->linkUnsubscribe }}"><span>{!! __('Unsubscribe') !!}</span></a></span>
                @else
          <span class="f-act-span"><a class="f-btn f-btn-subscribe f-opacity" title="{{ __('Subscribe forum') }}" href="{{ $p->model->linkSubscribe }}"><span>{!! __('Subscribe') !!}</span></a></span>
                @endif
            @endif
            @if ($p->model->canCreateTopic)
          <small>|</small>
          <span class="f-act-span"><a class="f-btn f-btn-create-topic" title="{{ __('Post topic') }}" href="{{ $p->model->linkCreateTopic }}"><span>{!! __('Post topic') !!}</span></a></span>
            @endif
        </div>
        @endif
        @yield ('pagination')
      </div>
    @endif
    @yield ('crumbs')
    </div>
@endif
@if ($p->enableMod && $form = $p->formMod)
    <section id="fork-mod" class="f-moderate">
      <h2>{!! __('Moderate') !!}</h2>
      <div class="f-fdivm">
    @include ('layouts/form')
      </div>
    </section>
@endif
