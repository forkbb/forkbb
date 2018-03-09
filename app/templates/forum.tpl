@section ('crumbs')
      <ul class="f-crumbs">
  @foreach ($p->crumbs as $cur)
        <li class="f-crumb"><!-- inline -->
    @if ($cur[0])
          <a href="{!! $cur[0] !!}" @if ($cur[2]) class="active" @endif>{{ $cur[1] }}</a>
    @else
          <span @if ($cur[2]) class="active" @endif>{{ $cur[1] }}</span>
    @endif
        </li><!-- endinline -->
  @endforeach
      </ul>
@endsection
@section ('pagination')
  @if ($p->model->pagination)
        <nav class="f-pages">
    @foreach ($p->model->pagination as $cur)
      @if ($cur[2])
          <a class="f-page active" href="{!! $cur[0] !!}">{{ $cur[1] }}</a>
      @elseif ('info' === $cur[1])
          <span class="f-pinfo">{!! $cur[0] !!}</span>
      @elseif ('space' === $cur[1])
          <span class="f-page f-pspacer">{!! __('Spacer') !!}</span>
      @elseif ('prev' === $cur[1])
          <a rel="prev" class="f-page f-pprev" href="{!! $cur[0] !!}">{!! __('Previous') !!}</a>
      @elseif ('next' === $cur[1])
          <a rel="next" class="f-page f-pnext" href="{!! $cur[0] !!}">{!! __('Next') !!}</a>
      @else
          <a class="f-page" href="{!! $cur[0] !!}">{{ $cur[1] }}</a>
      @endif
    @endforeach
        </nav>
  @endif
@endsection
@extends ('layouts/main')
@if ($forums = $p->model->subforums)
    <div class="f-nav-links">
  @yield ('crumbs')
    </div>
    <section class="f-subforums">
      <ol class="f-ftlist">
        <li id="id-subforums{!! $p->model->id !!}" class="f-category">
          <h2>{{ __('Sub forum', 2) }}</h2>
          <ol class="f-table">
            <li class="f-row f-thead" value="0">
              <div class="f-hcell f-cmain">{!! __('Sub forum', 1) !!}</div>
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
          <a class="f-btn f-btn-create-topic" href="{!! $p->model->linkCreateTopic !!}">{!! __('Post topic') !!}</a>
        </div>
  @endif
      </div>
@endif
    </div>
@if ($p->topics)
    <section class="f-main f-forum">
      <h2>{{ $p->model->forum_name or $p->model->name }}</h2>
      <div class="f-ftlist">
        <ol class="f-table">
          <li class="f-row f-thead" value="0">
            <div class="f-hcell f-cmain">{!! __('Topic', 1) !!}</div>
            <div class="f-hcell f-cstats">{!! __('Stats') !!}</div>
            <div class="f-hcell f-clast">{!! __('Last post') !!}</div>
          </li>
  @foreach ($p->topics as $id => $topic)
    @if (empty($topic->id) && $iswev = ['e' => [__('Topic %s was not found in the database', $id)]])
          <li id="topic-{!! $id !!}" class="f-row">
      @include ('layouts/iswev')
          </li>
    @elseif ($topic->moved_to)
          <li id="topic-{!! $topic->id !!}" class="f-row f-fredir">
            <div class="f-cell f-cmain">
              <div class="f-ficon"></div>
              <div class="f-finfo">
                <h3><span class="f-fredirtext">{!! __('Moved') !!}</span> <a class="f-ftname" href="{!! $topic->link !!}">{{ cens($topic->subject) }}</a></h3>
              </div>
            </div>
          </li>
    @else
          <li id="topic-{!! $topic->id !!}" class="f-row @if ($topic->hasNew !== false) f-fnew @endif @if ($topic->hasUnread !== false) f-funread @endif @if ($topic->sticky) f-fsticky @endif @if ($topic->closed) f-fclosed @endif @if ($topic->poll_type) f-fpoll @endif @if ($topic->dot) f-fposted @endif">
            <div class="f-cell f-cmain">
              <div class="f-ficon"></div>
              <div class="f-finfo">
                <h3>
      @if ($topic->dot)
                  <span class="f-tdot">·</span>
      @endif
      @if ($topic->sticky)
                  <span class="f-stickytxt">{!! __('Sticky') !!}</span>
      @endif
      @if ($topic->closed)
                  <span class="f-closedtxt">{!! __('Closed') !!}</span>
      @endif
      @if ($topic->poll_type)
                  <span class="f-polltxt">{!! __('Poll') !!}</span>
      @endif
                  <a class="f-ftname" href="{!! $topic->link !!}">{{ cens($topic->subject) }}</a>
      @if ($topic->pagination)
                  <span class="f-tpages">
        @foreach ($topic->pagination as $cur)
          @if ($cur[1] === 'space')
                    <span class="f-page f-pspacer">{!! __('Spacer') !!}</span>
          @else
                    <a class="f-page" href="{!! $cur[0] !!}">{{ $cur[1] }}</a>
           @endif
        @endforeach
                  </span>
      @endif
      @if ($topic->hasNew !== false)
                  <span class="f-newtxt"><a href="{!! $topic->linkNew !!}" title="{!! __('New posts info') !!}">{!! __('New posts') !!}</a></span>
      @endif
                </h3>
                <p class="f-cmposter">{!! __('by') !!} {{ $topic->poster }}</p>
      @if ($p->searchMode)
                <p class="f-cmforum"><a href="{!! $topic->parent->link !!}">{{ $topic->parent->forum_name }}</a></p>
      @endif
              </div>
            </div>
            <div class="f-cell f-cstats">
              <ul>
                <li>{!! __('%s Reply', $topic->num_replies, num($topic->num_replies)) !!}</li>
      @if ($topic->showViews)
                <li>{!! __('%s View', $topic->num_views, num($topic->num_views)) !!}</li>
      @endif
              </ul>
            </div>
            <div class="f-cell f-clast">
              <ul>
                <li class="f-cltopic"><a href="{!! $topic->linkLast !!}" title="&quot;{{ cens($topic->subject) }}&quot; - {!! __('Last post') !!}">{{ dt($topic->last_post) }}</a></li>
                <li class="f-clposter">{!! __('by') !!} {{ $topic->last_poster }}</li>
              </ul>
            </div>
          </li>
    @endif
  @endforeach
        </ol>
      </div>
    </section>
    <div class="f-nav-links">
  @if ($p->model->canCreateTopic || $p->model->pagination || $p->model->canMarkRead)
      <div class="f-nlinks-a">
    @if ($p->model->canCreateTopic || $p->model->canMarkRead)
        <div class="f-actions-links">
      @if ($p->model->canMarkRead)
          <a class="f-btn f-btn-markread" title="{!! __('Mark forum read') !!}" href="{!! $p->model->linkMarkRead !!}">{!! __('All is read') !!}</a>
      @endif
      @if ($p->model->canCreateTopic)
          <a class="f-btn f-btn-create-topic" href="{!! $p->model->linkCreateTopic !!}">{!! __('Post topic') !!}</a>
        </div>
      @endif
    @endif
    @yield ('pagination')
      </div>
  @endif
  @yield ('crumbs')
    </div>
@endif
