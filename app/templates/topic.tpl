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
@section ('linkpost')
  @if ($p->model->canReply || $p->model->closed)
        <div class="f-link-post">
    @if ($p->model->closed)
          {!! __('Topic closed') !!}
    @else
          <a class="f-btn" href="{!! $p->model->linkReply !!}">{!! __('Post reply') !!}</a>
    @endif
        </div>
  @endif
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
    <div class="f-nav-links">
@yield ('crumbs')
@if ($p->model->canReply || $p->model->closed || $p->model->pagination)
      <div class="f-links-b clearfix">
  @yield ('pagination')
  @yield ('linkpost')
      </div>
@endif
    </div>
    <section class="f-main f-topic">
@if ($p->searchMode)
      <h2>{{ $p->model->name }}</h2>
@else
      <h2>{{ cens($p->model->subject) }}</h2>
@endif
@foreach ($p->posts as $post)
      <article id="p{!! $post->id !!}" class="clearfix f-post @if ($post->user->gender == 1) f-user-male @elseif ($post->user->gender == 2) f-user-female @endif @if ($post->user->online) f-user-online @endif @if (1 === $post->postNumber && ! $p->searchMode) f-post-first @endif">
        <header class="f-post-header clearfix">
  @if ($p->searchMode)
          <h3>@if ($post->id !== $post->parent->first_post_id) {!! __('Re') !!} @endif {{ cens($post->parent->subject) }}</h3>
  @else
          <h3>@if ($post->postNumber > 1) {!! __('Re') !!} @endif {{ cens($p->model->subject) }}</h3>
  @endif
          <span class="f-post-posted"><a href="{!! $post->link !!}" rel="bookmark"><time datetime="{{ utc($post->posted) }}">{{ dt($post->posted) }}</time></a></span>
  @if ($post->edited)
          <span class="f-post-edited" title="{!! __('Last edit', $post->edited_by, dt($post->edited)) !!}">{!! __('Edited') !!}</span>
  @endif
          <span class="f-post-number">#{!! $post->postNumber !!}</span>
        </header>
        <div class="f-post-body clearfix">
          <address class="f-post-left clearfix">
            <ul class="f-user-info">
  @if ($post->showUserLink && $post->user->link)
              <li class="f-username"><a href="{!! $post->user->link !!}">{{ $post->user->username }}</a></li>
  @else
              <li class="f-username">{{ $post->user->username }}</li>
  @endif
  @if (! $p->searchMode && $post->showUserAvatar && $post->user->avatar)
              <li class="f-avatar">
                <img alt="{{ $post->user->username }}" src="{!! $post->user->avatar !!}">
              </li>
  @endif
              <li class="f-usertitle"><span>{{ $post->user->title() }}</span></li>
  @if (! $p->searchMode && $post->showPostCount && $post->user->num_posts)
              <li class="f-postcount"><span>{!! __('%s post', $post->user->num_posts, num($post->user->num_posts)) !!}</span></li>
  @endif
            </ul>
  @if (! $p->searchMode && $post->showUserInfo)
            <ul class="f-user-info-add">
              <li><span>{!! __('Registered:') !!} {{ dt($post->user->registered, true) }}</span></li>
    @if ($post->user->location)
              <li><span>{!! __('From') !!} {{ cens($post->user->location) }}</span></li>
    @endif
              <li><span></span></li>
            </ul>
  @endif
  @if ($p->searchMode)
            <ul class="f-post-search-info">
              <li class="f-psiforum"><span>{!! __('Forum') !!}: <a href="{!! $post->parent->parent->link !!}">{{ $post->parent->parent->forum_name }}</a></span></li>
              <li class="f-psitopic"><span>{!! __('Topic') !!}: <a href="{!! $post->parent->link !!}">{{ cens($post->parent->subject) }}</a></span></li>
              <li class="f-psireply"><span>{!! __('%s Reply', $post->parent->num_replies, num($post->parent->num_replies)) !!}</span></li>
    @if ($post->parent->showViews)
              <li class="f-psireply"><span>{!! __('%s View', $post->parent->num_views, num($post->parent->num_views)) !!}</span></li>
    @endif
            </ul>
  @endif
          </address>
          <div class="f-post-right f-post-main">
            {!! $post->html() !!}
          </div>
  @if (! $p->searchMode && $post->showSignature && $post->user->signature)
          <div class="f-post-right f-post-signature">
            <hr>
            {!! $post->user->htmlSign !!}
          </div>
  @endif
        </div>
        <footer class="f-post-footer clearfix">
          <div class="f-post-left">
            <span></span>
          </div>
  @if ($p->searchMode)
  @else
    @if ($post->canReport || $post->canDelete || $post->canEdit || $post->canQuote)
          <div class="f-post-right clearfix">
            <ul>
      @if ($post->canReport)
              <li class="f-postreport"><a class="f-btn f-minor" href="{!! $post->linkReport !!}">{!! __('Report') !!}</a></li>
      @endif
      @if ($post->canDelete)
              <li class="f-postdelete"><a class="f-btn" href="{!! $post->linkDelete !!}">{!! __('Delete') !!}</a></li>
      @endif
      @if ($post->canEdit)
              <li class="f-postedit"><a class="f-btn" href="{!! $post->linkEdit !!}">{!! __('Edit') !!}</a></li>
      @endif
      @if ($post->canQuote)
              <li class="f-postquote"><a class="f-btn" href="{!! $post->linkQuote !!}">{!! __('Quote') !!}</a></li>
      @endif
            </ul>
          </div>
    @endif
  @endif
        </footer>
      </article>
@endforeach
    </section>
    <div class="f-nav-links">
@if ($p->model->canReply || $p->model->closed || $p->model->pagination)
      <div class="f-links-a clearfix">
  @yield ('linkpost')
  @yield ('pagination')
      </div>
@endif
@yield ('crumbs')
    </div>
@if ($p->online)
  @include ('layouts/stats')
@endif
@if ($form = $p->form)
    <section class="f-post-form f-btnsrow-form">
      <h2>{!! __('Quick post') !!}</h2>
      <div class="f-fdiv">
  @include ('layouts/form')
      </div>
    </section>
@endif
