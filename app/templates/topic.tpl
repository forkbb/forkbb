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
  @if ($p->topic->canReply || $p->topic->closed)
        <div class="f-link-post">
    @if ($p->topic->closed)
          {!! __('Topic closed') !!}
    @else
          <a class="f-btn" href="{!! $p->topic->linkReply !!}">{!! __('Post reply') !!}</a>
    @endif
        </div>
  @endif
@endsection
@section ('pagination')
        <nav class="f-pages">
  @foreach ($p->topic->pagination as $cur)
    @if ($cur[2])
          <span class="f-page active">{{ $cur[1] }}</span>
    @elseif ($cur[1] === 'space')
          <span class="f-page f-pspacer">{!! __('Spacer') !!}</span>
    @elseif ($cur[1] === 'prev')
          <a rel="prev" class="f-page f-pprev" href="{!! $cur[0] !!}">{!! __('Previous') !!}</a>
    @elseif ($cur[1] === 'next')
          <a rel="next" class="f-page f-pnext" href="{!! $cur[0] !!}">{!! __('Next') !!}</a>
    @else
          <a class="f-page" href="{!! $cur[0] !!}">{{ $cur[1] }}</a>
    @endif
  @endforeach
        </nav>
@endsection
@extends ('layouts/main')
    <div class="f-nav-links">
@yield ('crumbs')
@if ($p->topic->canReply || $p->topic->closed || $p->topic->pagination)
      <div class="f-links-b clearfix">
  @yield ('pagination')
  @yield ('linkpost')
      </div>
@endif
    </div>
    <section class="f-main f-topic">
      <h2>{{ cens($p->topic->subject) }}</h2>
@foreach ($p->posts as $post)
      <article id="p{!! $post->id !!}" class="clearfix f-post @if ($post->user->gender == 1) f-user-male @elseif ($post->user->gender == 2) f-user-female @endif @if ($post->user->online) f-user-online @endif">
        <header class="f-post-header clearfix">
          <h3>{{ cens($p->topic->subject) }} - #{!! $post->postNumber !!}</h3>
          <span class="f-post-posted"><time datetime="{{ utc($post->posted) }}">{{ dt($post->posted) }}</time></span>
  @if ($post->edited)
          <span class="f-post-edited" title="{!! __('Last edit', $post->edited_by, dt($post->edited)) !!}">{!! __('Edited') !!}</span>
  @endif
          <span class="f-post-number"><a href="{!! $post->link !!}" rel="bookmark">#{!! $post->postNumber !!}</a></span>
        </header>
        <div class="f-post-body clearfix">
          <address class="f-post-left clearfix">
            <ul class="f-user-info">
  @if ($post->showUserLink && $post->user->link)
              <li class="f-username"><a href="{!! $post->user->link !!}">{{ $post->user->username }}</a></li>
  @else
              <li class="f-username">{{ $post->user->username }}</li>
  @endif
  @if ($post->showUserAvatar && $post->user->avatar)
              <li class="f-avatar">
                <img alt="{{ $post->user->username }}" src="{!! $post->user->avatar !!}">
              </li>
  @endif
              <li class="f-usertitle"><span>{{ $post->user->title() }}</span></li>
  @if ($post->showPostCount && $post->user->num_posts)
              <li class="f-postcount"><span>{!! __('%s post', $post->user->num_posts, num($post->user->num_posts)) !!}</span></li>
  @endif
            </ul>
  @if ($post->showUserInfo)
            <ul class="f-user-info-add">
              <li><span>{!! __('Registered:') !!} {{ dt($post->user->registered, true) }}</span></li>
    @if ($post->user->location)
              <li><span>{!! __('From') !!} {{ cens($post->user->location) }}</span></li>
    @endif
              <li><span></span></li>
            </ul>
  @endif
          </address>
          <div class="f-post-right f-post-main">
            {!! $post->html() !!}
          </div>
  @if ($post->showSignature && $post->user->signature)
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
        </footer>
      </article>
@endforeach
    </section>
    <div class="f-nav-links">
@if ($p->topic->canReply || $p->topic->closed || $p->topic->pagination)
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
